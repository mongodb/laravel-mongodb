<?php namespace Jenssegers\Mongodb;

use MongoID;
use MongoRegex;

class Builder extends \Illuminate\Database\Query\Builder {

    /**
    * The database collection
    *
    * @var MongoCollection
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
        '<>' => '$ne',
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
     * Execute the query as a fresh "select" statement.
     *
     * @param  array  $columns
     * @return array|static[]
     */
    public function getFresh($columns = array('*'))
    {
        // If no columns have been specified for the select statement, we will set them
        // here to either the passed columns, or the standard default of retrieving
        // all of the columns on the table using the "wildcard" column character.
        if (is_null($this->columns)) $this->columns = $columns;

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
            $group = array();

            // Grouping
            if ($this->groups)
            {
                foreach ($this->groups as $column)
                {
                    $group['_id'][$column] = '$' . $column;
                    $group[$column] = array('$last' => '$' . $column);
                }
            }
            else
            {
                $group['_id'] = 0;
            }

            // Columns
            foreach ($this->columns as $column)
            {
                $group[$column] = array('$last' => '$' . $column);
            }

            // Apply aggregation functions
            if ($this->aggregate)
            {
                $function = $this->aggregate['function'];

                foreach ($this->aggregate['columns'] as $column)
                {
                    if ($function == 'count')
                    {
                        $group[$column] = array('$sum' => 1);
                    }
                    else {
                        $group[$column] = array('$' . $function => '$' . $column);
                    }
                }
            }

            if ($wheres) $pipeline[] = array('$match' => $wheres);

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
            // Execute distinct
            if ($this->distinct)
            {
                $column = isset($this->columns[0]) ? $this->columns[0] : '_id';
                return $this->collection->distinct($column, $wheres);
            }

            // Columns
            $columns = array();
            foreach ($this->columns as $column)
            {
                $columns[$column] = true;
            }

            // Get the MongoCursor
            $cursor = $this->collection->find($wheres, $columns);

            // Apply order, offset and limit
            if ($this->orders) $cursor->sort($this->orders);
            if ($this->offset) $cursor->skip($this->offset);
            if ($this->limit) $cursor->limit($this->limit);

            // Return results
            return iterator_to_array($cursor);
        }
    }

    /**
     * Generate the unique cache key for the query.
     *
     * @return string
     */
    public function generateCacheKey()
    {
        $key = array(
            'connection' => $this->connection->getName(),
            'collection' => $this->collection->getName(),
            'wheres'     => $this->wheres,
            'columns'    => $this->columns,
            'groups'     => $this->groups,
            'orders'     => $this->orders,
            'offset'     => $this->offset,
            'aggregate'  => $this->aggregate,
        );

        return md5(serialize(array_values($key)));
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

        // Once we have executed the query, we will reset the aggregate property so
        // that more select queries can be executed against the database without
        // the aggregate value getting in the way when the grammar builds it.
        $this->columns = null; $this->aggregate = null;

        if (isset($results[0]))
        {
            return $results[0][$columns[0]];
        }
    }

    /**
     * Force the query to only return distinct results.
     *
     * @return Builder
     */
    public function distinct($column = false)
    {
        $this->distinct = true;

        if ($column)
        {
            $this->columns = array($column);
        }

        return $this;
    }

    /**
     * Add an "order by" clause to the query.
     *
     * @param  string  $column
     * @param  string  $direction
     * @return Builder
     */
    public function orderBy($column, $direction = 'asc')
    {
        $this->orders[$column] = ($direction == 'asc' ? 1 : -1);

        return $this;
    }

    /**
     * Add a where between statement to the query.
     *
     * @param  string  $column
     * @param  array   $values
     * @param  string  $boolean
     * @return \Illuminate\Database\Query\Builder
     */
    public function whereBetween($column, array $values, $boolean = 'and')
    {
        $type = 'between';

        $this->wheres[] = compact('column', 'type', 'boolean', 'values');

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
        // Since every insert gets treated like a batch insert, we will make sure the
        // bindings are structured in a way that is convenient for building these
        // inserts statements by verifying the elements are actually an array.
        if ( ! is_array(reset($values)))
        {
            $values = array($values);
        }

        // Batch insert
        return $this->collection->batchInsert($values);
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
        $result = $this->collection->insert($values);

        if (1 == (int) $result['ok'])
        {
            if (!$sequence)
            {
                $sequence = '_id';
            }

            // Return id as a string
            return (string) $values[$sequence];
        }
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

        if (1 == (int) $result['ok'])
        {
            return $result['n'];
        }

        return 0;
    }

    /**
     * Increment a column's value by a given amount.
     *
     * @param  string  $column
     * @param  int     $amount
     * @param  array   $extra
     * @return int
     */
    public function increment($column, $amount = 1, array $extra = array())
    {
        // build update statement
        $update = array(
            '$inc' => array($column => $amount),
            '$set' => $extra,
        );

        // protect
        $this->whereNotNull($column);

        // perform
        $result = $this->collection->update($this->compileWheres(), $update, array('multiple' => true));

        if (1 == (int) $result['ok'])
        {
            return $result['n'];
        }

        return 0;
    }

    /**
     * Decrement a column's value by a given amount.
     *
     * @param  string  $column
     * @param  int     $amount
     * @param  array   $extra
     * @return int
     */
    public function decrement($column, $amount = 1, array $extra = array())
    {
        return $this->increment($column, -1 * $amount, $extra);
    }

    /**
     * Delete a record from the database.
     *
     * @param  mixed  $id
     * @return int
     */
    public function delete($id = null)
    {
        $result = $this->collection->remove($this->compileWheres());

        if (1 == (int) $result['ok'])
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
     * Run a truncate statement on the table.
     *
     * @return void
     */
    public function truncate()
    {
        $result = $this->collection->drop();

        return (1 == (int) $result['ok']);
    }

    /**
     * Get a new instance of the query builder.
     *
     * @return Builder
     */
    public function newQuery()
    {
        return new Builder($this->connection);
    }

    /**
    * Compile the where array
    *
    * @return array
    */
    private function compileWheres()
    {
        if (!$this->wheres) return array();

        // The new list of compiled wheres
        $wheres = array();

        foreach ($this->wheres as $i=>&$where) 
        {
            // Convert id's
            if (isset($where['column']) && $where['column'] == '_id')
            {
                if (isset($where['values']))
                {
                    foreach ($where['values'] as &$value)
                    {
                        $value = ($value instanceof MongoID) ? $value : new MongoID($value);
                    }
                }
                else
                {
                    $where['value'] = ($where['value'] instanceof MongoID) ? $where['value'] : new MongoID($where['value']);
                }
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

            // Check for or
            if ($where['boolean'] == 'or')
            {
                $compiled = array('$or' => array($compiled));
            }

            // Merge compiled where
            $wheres = array_merge_recursive($wheres, $compiled);
        }

        return $wheres;
    }

    private function compileWhereBasic($where)
    {
        extract($where);

        // Replace like with MongoRegex
        if ($operator == 'like')
        {
            $operator = '=';
            $regex = str_replace('%', '', $value);

            // Prepare regex
            if (substr($value, 0, 1) != '%') $regex = '^' . $regex;
            if (substr($value, -1) != '%')   $regex = $regex . '$';

            $value = new MongoRegex("/$regex/i");
        }

        if (!isset($operator) || $operator == '=')
        {
            $query = array($column => $value);
        }
        else
        {
            $query = array($column => array($this->conversion[$operator] => $value));
        }

        return $query;
    }

    private function compileWhereNested($where)
    {
        extract($where);

        return $query->compileWheres();
    }

    private function compileWhereIn($where)
    {
        extract($where);

        return array($column => array('$in' => $values));
    }

    private function compileWhereNotIn($where)
    {
        extract($where);

        return array($column => array('$nin' => $values));
    }

    private function compileWhereNull($where)
    {
        $where['operator'] = '=';
        $where['value'] = null;

        return $this->compileWhereBasic($where);
    }

    private function compileWhereNotNull($where)
    {
        $where['operator'] = '!=';
        $where['value'] = null;

        return $this->compileWhereBasic($where);
    }

    private function compileWhereBetween($where)
    {
        extract($where);

        return array(
            $column => array(
                '$gte' => $values[0],
                '$lte' => $values[1])
            );
    }

    private function compileWhereRaw($where)
    {
        return $where['sql'];
    }

}