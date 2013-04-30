<?php namespace Jenssegers\Mongodb;

use MongoID;

class Query extends \Illuminate\Database\Query\Builder {

    /**
    * The database collection
    *
    * @var string
    */
    public $collection;

    /**
    * All of the available operators.
    *
    * @var array
    */
    protected $conversion = array(
        '=' => '=',
        '!=' => '$ne',
        '<' => '$lt',
        '<=' => '$lte',
        '>' => '$gt',
        '>=' => '$gte',
    );

    /**
    * Create a new query builder instance.
    *
    * @param Connection $connection
    * @return void
    */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Execute a query for a single record by ID.
     *
     * @param  int    $id
     * @param  array  $columns
     * @return mixed
     */
    public function find($id, $columns = array('*'))
    {
        return $this->where('_id', '=', new MongoID((string) $id))->first($columns);
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array  $columns
     * @return array
     */
    public function get($columns = array('*'))
    {
        // If no columns have been specified for the select statement, we will set them
        // here to either the passed columns, or the standard default of retrieving
        // all of the columns on the table using the "wildcard" column character.
        if (is_null($this->columns))
        {
            $this->columns = $columns;
        }

        // Drop all columns if * is present
        if (in_array('*', $this->columns)) 
        {
            $this->columns = array();
        }

        // Compile wheres
        $wheres = $this->compileWheres();

        // Use aggregation framework if needed
        if ($this->groups || $this->aggregate)
        {
            $pipeline = array();

            // Group
            $group = array();
            $group['_id'] = $this->groups ? $this->groups : 0;

            // Columns
            foreach ($this->columns as $column)
            {
                $group[$column] = array('$last' => '$' . $column);
            }

            // Apply aggregation functions
            if ($this->aggregate)
            {
                foreach ($this->aggregate['columns'] as $column)
                {
                    $group[$column] = array('$' . $this->aggregate['function'] => '$' . $column);
                }
            }

            if ($wheres) $pipeline[] = array('$match' => $match);

            $pipeline[] = array('$group' => $group);

            // Apply order and limit
            if ($this->orders) $pipeline[] = array('$sort' => $this->orders);
            if ($this->limit) $pipeline[] = array('$limit' => $this->limit);

            $results = $this->collection->aggregate($pipeline);

            // Return results
            return $results['result'];
        }
        else
        {
            // Get the MongoCursor
            $cursor = $this->collection->find($wheres, $this->columns);

            // Apply order, offset and limit
            if ($this->orders) $cursor->sort($this->orders);
            if ($this->offset) $cursor->skip($this->offset);
            if ($this->limit) $cursor->limit($this->limit);

            // Return results
            return $cursor;
        }
    }

    /**
     * Execute an aggregate function on the database.
     *
     * @param  string  $function
     * @param  array   $columns
     * @return mixed
     */
    public function aggregate($function, $columns = array('*'))
    {
        $this->aggregate = compact('function', 'columns');

        $results = $this->get($columns);

        if (isset($results[0]))
        {
            return $results[0][$columns[0]];
        }
    }

    /**
     * Add an "order by" clause to the query.
     *
     * @param  string  $column
     * @param  string  $direction
     * @return \Illuminate\Database\Query\Builder
     */
    public function orderBy($column, $direction = 'asc')
    {
        $this->orders[$column] = ($direction == 'asc' ? 1 : -1);

        return $this;
    }

    /**
     * Add a "group by" clause to the query.
     *
     * @param  dynamic  $columns
     * @return \Illuminate\Database\Query\Builder
     */
    public function groupBy()
    {
        $groups = func_get_args();

        foreach ($groups as $group)
        {
            $this->groups[$group] = '$' . $group;
        }

        return $this;
    }

    /**
     * Insert a new record into the database.
     *
     * @param  array  $values
     * @return bool
     */
    public function insert(array $values)
    {
        $result = $this->collection->insert($values);

        if(1 == (int) $result['ok'])
        {
            return $values['_id'];
        }
    }

    /**
     * Insert a new record and get the value of the primary key.
     *
     * @param  array   $values
     * @param  string  $sequence
     * @return int
     */
    public function insertGetId(array $values, $sequence = null)
    {
        return $this->insert($values);
    }

    /**
     * Update a record in the database.
     *
     * @param  array  $values
     * @return int
     */
    public function update(array $values)
    {
        $update = array('$set' => $values);

        $result = $this->collection->update($this->compileWheres(), $update, array('multiple' => true));

        if(1 == (int) $result['ok'])
        {
            return $result['n'];
        }

        return 0;
    }

    /**
     * Delete a record from the database.
     *
     * @param  mixed  $id
     * @return int
     */
    public function delete($id = null)
    {
        $query = $this->compileWheres($this);
        $result = $this->collection->remove($query);

        if(1 == (int) $result['ok'])
        {
            return $result['n'];
        }

        return 0;
    }

    /**
     * Set the collection which the query is targeting.
     *
     * @param  string  $collection
     * @return Builder
     */
    public function from($collection)
    {
        if ($collection)
        {
            $this->collection = $this->connection->getCollection($collection);
        }

        return $this;
    }

    /**
    * Compile the where array
    *
    * @return array
    */
    public function compileWheres()
    {
        if (!$this->wheres) return array();
        
        $wheres = array();

        foreach ($this->wheres as $i=>&$where) 
        {
            // Convert id's
            if (isset($where['column']) && $where['column'] == '_id')
            {
                $where['value'] = ($where['value'] instanceof MongoID) ? $where['value'] : new MongoID($where['value']);
            }

            // First item of chain
            if ($i == 0 && count($this->wheres) > 1 && $where['boolean'] == 'and')
            {
                // Copy over boolean value of next item in chain
                $where['boolean'] = $this->wheres[$i+1]['boolean'];
            }

            // Delegate
            $method = "compileWhere{$where['type']}";
            $compiled = $this->{$method}($where);

            // Merge compiled where
            $wheres = array_merge_recursive($wheres, $compiled);
        }

        return $wheres;
    }

    public function compileWhereBasic($where)
    {
        extract($where);

        if (!isset($operator) || $operator == '=')
        {
            $query = array($column => $value);
        }
        else
        {
            $query = array($column => array($this->conversion[$operator] => $value));
        }

        if ($boolean == 'or')
        {
            return array('$or' => array($query));
        }

        return $query;
    }

    public function compileWhereNested($where)
    {
        extract($where);

        // Compile subquery
        $compiled = $query->compileWheres();

        if ($boolean == 'or')
        {
            return array('$or' => array($compiled));
        }

        return $compiled;
    }

    public function compileWhereIn($where)
    {
        extract($where);

        return array($column => array('$in' => $values));
    }

    public function compileWhereNull($where)
    {
        $where['operator'] = '=';
        $where['value'] = null;

        return $this->compileWhereBasic($where);
    }

    public function compileWhereNotNull($where)
    {
        $where['operator'] = '!=';
        $where['value'] = null;

        return $this->compileWhereBasic($where);
    }

    /**
     * Get a new instance of the query builder.
     *
     * @return Builder
     */
    public function newQuery()
    {
        $connection = $this->getConnection();
        return new Query($connection);
    }

}