<?php namespace Jenssegers\Mongodb;

use MongoID;
use MongoRegex;
use Closure;

class Builder extends \Illuminate\Database\Query\Builder {

    /**
    * The database collection
    *
    * @var MongoCollection
    */
    protected $collection;

    /**
    * All of the available operators.
    *
    * @var array
    */
    protected $conversion = array(
        '='  => '=',
        '!=' => '$ne',
        '<>' => '$ne',
        '<'  => '$lt',
        '<=' => '$lte',
        '>'  => '$gt',
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
        return $this->where('_id', '=', $this->convertKey($id))->first($columns);
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

        // Drop all columns if * is present, MongoDB does not work this way
        if (in_array('*', $this->columns)) $this->columns = array();

        // Compile wheres
        $wheres = $this->compileWheres();

        // Aggregation query
        if ($this->groups || $this->aggregate)
        {
            $group = array();

            // Apply grouping
            if ($this->groups)
            {
                foreach ($this->groups as $column)
                {
                    // Mark column as grouped
                    $group['_id'][$column] = '$' . $column;

                    // Aggregate by $last when grouping, this mimics MySQL's behaviour a bit
                    $group[$column] = array('$last' => '$' . $column);
                }
            }
            else
            {
                // If we don't use grouping, set the _id to null to prepare the pipeline for 
                // other aggregation functions
                $group['_id'] = null;
            }

            // When additional columns are requested, aggregate them by $last as well
            foreach ($this->columns as $column)
            {
                // Replace possible dots in subdocument queries with underscores
                $key = str_replace('.', '_', $column);
                $group[$key] = array('$last' => '$' . $column);
            }

            // Apply aggregation functions, these may override previous aggregations
            if ($this->aggregate)
            {
                $function = $this->aggregate['function'];

                foreach ($this->aggregate['columns'] as $column)
                {
                    // Replace possible dots in subdocument queries with underscores
                    $key = str_replace('.', '_', $column);

                    // Translate count into sum
                    if ($function == 'count')
                    {
                        $group[$key] = array('$sum' => 1);
                    }
                    // Pass other functions directly
                    else
                    {
                        $group[$key] = array('$' . $function => '$' . $column);
                    }
                }
            }

            // Build pipeline
            $pipeline = array();
            if ($wheres)  $pipeline[] = array('$match' => $wheres);
            $pipeline[] = array('$group' => $group);

            // Apply order and limit
            if ($this->orders) $pipeline[] = array('$sort' => $this->orders);
            if ($this->offset) $pipeline[] = array('$skip' => $this->offset);
            if ($this->limit)  $pipeline[] = array('$limit' => $this->limit);

            // Execute aggregation
            $results = $this->collection->aggregate($pipeline);

            // Return results
            return $results['result'];
        }

        // Distinct query
        else if ($this->distinct)
        {
            // Return distinct results directly
            $column = isset($this->columns[0]) ? $this->columns[0] : '_id';
            return $this->collection->distinct($column, $wheres);
        }
        
        // Normal query
        else
        {
            $columns = array();
            foreach ($this->columns as $column)
            {
                $columns[$column] = true;
            }

            // Execute query and get MongoCursor
            $cursor = $this->collection->find($wheres, $columns);

            // Apply order, offset and limit
            if ($this->orders) $cursor->sort($this->orders);
            if ($this->offset) $cursor->skip($this->offset);
            if ($this->limit)  $cursor->limit($this->limit);

            // Return results as an array with numeric keys
            return iterator_to_array($cursor, false);
        }
    }

    /**
     * Generate the unique cache key for the current query.
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
            // Replace possible dots in subdocument queries with underscores
            $key = str_replace('.', '_', $columns[0]);
            return $results[0][$key];
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
        // Since every insert gets treated like a batch insert, we will have to detect
        // if the user is inserting a single document or an array of documents.
        $batch = true;
        foreach ($values as $value)
        {
            // As soon as we find a value that is not an array we assume the user is
            // inserting a single document.
            if (!is_array($value)) 
            {
                $batch = false; break;
            }
        }

        if (!$batch) $values = array($values);

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
     * @param  array  $options
     * @return int
     */
    public function update(array $values, array $options = array())
    {
        return $this->performUpdate(array('$set' => $values), $options);
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
     * Pluck a single column from the database.
     *
     * @param  string  $column
     * @return mixed
     */
    public function pluck($column)
    {
        $result = (array) $this->first(array($column));

        // MongoDB returns the _id field even if you did not ask for it, so we need to
        // remove this from the result.
        if (array_key_exists('_id', $result))
        {
            unset($result['_id']);
        }

        return count($result) > 0 ? reset($result) : null;
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
     * Create a raw database expression.
     *
     * @param  closure  $expression
     * @return mixed
     */
    public function raw($expression = null)
    {
        if ($expression instanceof Closure)
        {
            return call_user_func($expression, $this->collection);
        }

        return $this->collection;
    }

    /**
     * Append one or more values to an array.
     *
     * @param  mixed   $column
     * @param  mixed   $value
     * @return int
     */
    public function push($column, $value = null)
    {
        if (is_array($column))
        {
            $query = array('$push' => $column);
        }
        else if (is_array($value))
        {
            // $pushAll depricated
            $query = array('$push' => array($column => array('$each' => $value)));
        }
        else
        {
            $query = array('$push' => array($column => $value));
        }

        return $this->performUpdate($query);
    }

    /**
     * Remove one or more values from an array.
     *
     * @param  mixed   $column
     * @param  mixed   $value
     * @return int
     */
    public function pull($column, $value = null)
    {
        if (is_array($column))
        {
            $query = array('$pull' => $column);
        }
        else if (is_array($value))
        {
            $query = array('$pullAll' => array($column => $value));
        }
        else
        {
            $query = array('$pull' => array($column => $value));
        }

        return $this->performUpdate($query);
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
     * Perform update.
     *
     * @param  array  $query
     * @param  array  $options
     * @return int
     */
    protected function performUpdate($query, array $options = array())
    {
        // Default options
        $default = array('multiple' => true);

        // Merge options and override default options
        $options = array_merge($default, $options);

        $result = $this->collection->update($this->compileWheres(), $query, $options);

        if (1 == (int) $result['ok'])
        {
            return $result['n'];
        }

        return 0;
    }

    /**
     * Convert a key to MongoID if needed
     * 
     * @param  mixed $id
     * @return mixed
     */
    protected function convertKey($id)
    {
        if (is_string($id) && strlen($id) === 24 && ctype_xdigit($id))
        {
            return new MongoId($id);
        }

        return $id;
    }

    /**
    * Compile the where array
    *
    * @return array
    */
    protected function compileWheres()
    {
        if (!$this->wheres) return array();

        // The new list of compiled wheres
        $wheres = array();

        foreach ($this->wheres as $i => &$where) 
        {
            // Convert id's
            if (isset($where['column']) && $where['column'] == '_id')
            {
                // Multiple values
                if (isset($where['values']))
                {
                    foreach ($where['values'] as &$value)
                    {
                        $value = $this->convertKey($value);
                    }
                }
                // Single value
                else
                {
                    $where['value'] = $this->convertKey($where['value']);
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

    protected function compileWhereBasic($where)
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

    protected function compileWhereNested($where)
    {
        extract($where);

        return $query->compileWheres();
    }

    protected function compileWhereIn($where)
    {
        extract($where);

        return array($column => array('$in' => $values));
    }

    protected function compileWhereNotIn($where)
    {
        extract($where);

        return array($column => array('$nin' => $values));
    }

    protected function compileWhereNull($where)
    {
        $where['operator'] = '=';
        $where['value'] = null;

        return $this->compileWhereBasic($where);
    }

    protected function compileWhereNotNull($where)
    {
        $where['operator'] = '!=';
        $where['value'] = null;

        return $this->compileWhereBasic($where);
    }

    protected function compileWhereBetween($where)
    {
        extract($where);

        return array(
            $column => array(
                '$gte' => $values[0],
                '$lte' => $values[1])
            );
    }

    protected function compileWhereRaw($where)
    {
        return $where['sql'];
    }

}