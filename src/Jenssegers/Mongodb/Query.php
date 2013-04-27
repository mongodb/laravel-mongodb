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
    protected $operators = array(
        '=' => '=',
        '!=' => '!=',
        '<' => '$lt',
        '<=' => '$le',
        '>' => '$gt',
        '>=' => '$ge',
        'in' => '$in',
        'exists' => '$exists'
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
        if (in_array('*', $this->columns)) $this->columns = array();

        // Get Mongo cursor
        if ($this->distinct)
        {
            $cursor = $this->collection->distinct($this->columns, $this->compileWheres());
        }
        else if(count($this->groups))
        {
            $options = array();
            $options['condition'] = $this->compileWheres();
            
            $result = $this->collection->group($this->groups, array(), NULL, $options);
            $cursor = $result['retval'];
        }
        else
        {
            $cursor = $this->collection->find($this->compileWheres(), $this->columns);
        }

        // Apply order
        if ($this->orders)
        {
            $cursor->sort($this->orders);
        }

        // Apply offset
        if ($this->offset)
        {
            $cursor->skip($this->offset);
        }

        // Apply limit
        if ($this->limit)
        {
            $cursor->limit($this->limit);
        }

        return $cursor;
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
        $this->collection = $this->connection->getCollection($collection);

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

        foreach ($this->wheres as $where) 
        {
            // Delegate
            $method = "compileWhere{$where['type']}";
            $compiled = $this->{$method}($where);

            foreach ($compiled as $key=>$value)
                $wheres[$key] = $value;
        }

        return $wheres;
    }

    public function compileWhereBasic($where)
    {
        extract($where);

        // Convert id's
        if ($column == '_id')
        {
            $value = ($value instanceof MongoID) ? $value : new MongoID($value);
        }

        // Convert operators
        if (!isset($operator) || $operator == '=')
        {
            return array($column => $value);
        }
        else
        {
            return array($column => array($this->operators[$operator] => $value));
        }
    }

    public function compileWhereIn($where)
    {
        extract($where);

        // Convert id's
        if ($column == '_id')
        {
            foreach ($values as &$value)
                $value = ($value instanceof MongoID) ? $value : new MongoID($value);
        }

        return array($column => array($this->operators['in'] => $values));
    }

    public function compileWhereNull($where)
    {
        extract($where);

        // Convert id's
        if ($column == '_id')
        {
            foreach ($values as &$value)
                $value = ($value instanceof MongoID) ? $value : new MongoID($value);
        }

        return array($column => array($this->operators['exists'] => false));
    }

    public function compileWhereNotNull($where)
    {
        extract($where);

        // Convert id's
        if ($column == '_id')
        {
            foreach ($values as &$value)
                $value = ($value instanceof MongoID) ? $value : new MongoID($value);
        }

        return array($column => array($this->operators['exists'] => true));
    }

}
