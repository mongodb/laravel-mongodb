<?php namespace Jenssegers\Mongodb;

use MongoID;

class Query {

    /**
     * The model.
     *
     * @var Jenssegers\Mongodb\Model
     */
    protected $model;

    /**
     * The database connection instance.
     *
     * @var Jenssegers\Mongodb\Connection
     */
    protected $connection;

    /**
     * The database collection used for the current query.
     *
     * @var string   $collection
     */
    protected $collection;

    /**
     * The where constraints for the query.
     *
     * @var array
     */
    public $wheres = array();

    /**
     * The columns that should be returned.
     *
     * @var array
     */
    public $columns = array();

    /**
     * The orderings for the query.
     *
     * @var array
     */
    public $orders = array();

    /**
     * The maximum number of documents to return.
     *
     * @var int
     */
    public $limit;

    /**
     * The number of records to skip.
     *
     * @var int
     */
    public $offset;

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
        '>=' => '$ge'
    );

    /**
     * Create a new model query builder instance.
     *
     * @param  Jenssegers\Mongodb\Connection  $connection
     * @return void
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
        $this->connection = $model->getConnection();
        $this->collection = $this->connection->getCollection($model->getCollection());
    }

    /**
     * Set the "limit" value of the query.
     *
     * @param  int  $value
     * @return \Jenssegers\Mongodb\Query
     */
    public function take($value)
    {
        $this->limit = $value;

        return $this;
    }

    /**
     * Set the "offset" value of the query.
     *
     * @param  int  $value
     * @return \Illuminate\Database\Query\Builder
     */
    public function skip($value)
    {
        $this->offset = $value;

        return $this;
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
     * Execute a query for a single record by ID.
     *
     * @param  int    $id
     * @param  array  $columns
     * @return mixed
     */
    public function find($id, $columns = array('*'))
    {
        $id = new MongoID((string) $id);

        return $this->where($this->model->getKeyName(), '=', $id)->first($columns);
    }

    /**
     * Execute the query and get the first result.
     *
     * @param  array   $columns
     * @return array
     */
    public function first($columns = array())
    {
        return $this->take(1)->get($columns)->first();
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @param  string  $boolean
     * @return \Jenssegers\Mongodb\Query
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if (!in_array(strtolower($operator), $this->operators, true))
        {
            list($value, $operator) = array($operator, '=');
        }

        if ($operator == '=')
        {
            $this->wheres[$column] = $value;
        }
        else
        {
            $this->wheres[$column] = array($this->operators[$operator] => $value);
        }

        return $this;
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array  $columns
     * @return Collection
     */
    public function get($columns = array())
    {
        // Merge with previous columns
        $this->columns = array_merge($this->columns, $columns);

        // Drop all columns if * is present
        if (in_array('*', $this->columns)) $this->columns = array();

        // Get Mongo cursor
        $cursor = $this->collection->find($this->wheres, $this->columns);

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

        // Return collection of models
        return $this->toCollection($cursor);
    }

    /**
     * Insert a new document into the database.
     *
     * @param  array  $data
     * @return mixed
     */
    public function insert($data)
    {
        $result = $this->collection->insert($data);

        if(1 == (int) $result['ok'])
        {
            return $data['_id'];
        }

        return false;
    }

    /**
     * Save the document. Insert into the database if its not exists.
     *
     * @param  array  $data
     * @return mixed
     */
    public function save($data)
    {
        $this->collection->save($data);

        if(isset($data['_id']))
        {
            return $data['_id'];
        }

        return false;
    }

    /**
     * Update a document in the database.
     *
     * @param  array  $data
     * @return int
     */
    public function update(array $data)
    {
        $update = array('$set' => $data);

        $result = $this->collection->update($this->wheres, $update, array('multiple' => true));

        if(1 == (int) $result['ok'])
        {
            return $result['n'];
        }

        return 0;
    }

    /**
     * Delete a document from the database.
     *
     * @return int
     */
    public function delete()
    {
        $result = $this->collection->remove($this->wheres);

        if(1 == (int) $result['ok'])
        {
            return $result['n'];
        }

        return 0;
    }

    /**
     * Transform to model collection.
     *
     * @param  array  $columns
     * @return Collection
     */
    public function toCollection($results)
    {
        $models = array();

        foreach ($results as $result) {
            $models[] = $this->model->newInstance($result);
        }

        return $this->model->newCollection($models);
    }

}