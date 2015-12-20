<?php namespace Jenssegers\Mongodb\Schema;

use Closure;
use Illuminate\Database\Connection;

class Blueprint extends \Illuminate\Database\Schema\Blueprint {

    /**
     * The MongoConnection object for this blueprint.
     *
     * @var MongoConnection
     */
    protected $connection;

    /**
     * The MongoCollection object for this blueprint.
     *
     * @var MongoCollection
     */
    protected $collection;

    /**
     * Fluent columns.
     *
     * @var array
     */
    protected $columns = [];

    /**
     * Create a new schema blueprint.
     *
     * @param  string   $table
     * @param  Closure  $callback
     */
    public function __construct(Connection $connection, $collection)
    {
        $this->connection = $connection;

        $this->collection = $connection->getCollection($collection);
    }

    /**
     * Specify an index for the collection.
     *
     * @param  string|array  $columns
     * @param  array         $options
     * @return Blueprint
     */
    public function index($columns = null, $options = [])
    {
        $columns = $this->fluent($columns);

        // Columns are passed as a default array.
        if (is_array($columns) && is_int(key($columns)))
        {
            // Transform the columns to the required array format.
            $transform = [];

            foreach ($columns as $column)
            {
                $transform[$column] = 1;
            }

            $columns = $transform;
        }

        $this->collection->ensureIndex($columns, $options);

        return $this;
    }

    /**
     * Specify the primary key(s) for the table.
     *
     * @param  string|array  $columns
     * @param  array         $options
     * @return \Illuminate\Support\Fluent
     */
    public function primary($columns = null, $options = [])
    {
        return $this->unique($columns, $options);
    }

    /**
     * Indicate that the given index should be dropped.
     *
     * @param  string|array  $columns
     * @return Blueprint
     */
    public function dropIndex($columns = null)
    {
        $columns = $this->fluent($columns);

        // Columns are passed as a default array.
        if (is_array($columns) && is_int(key($columns)))
        {
            // Transform the columns to the required array format.
            $transform = [];

            foreach ($columns as $column)
            {
                $transform[$column] = 1;
            }

            $columns = $transform;
        }

        $this->collection->deleteIndex($columns);

        return $this;
    }

    /**
     * Specify a unique index for the collection.
     *
     * @param  string|array  $columns
     * @param  array         $options
     * @return Blueprint
     */
    public function unique($columns = null, $options = [])
    {
        $columns = $this->fluent($columns);

        $options['unique'] = true;

        $this->index($columns, $options);

        return $this;
    }

    /**
     * Specify a non blocking index for the collection.
     *
     * @param  string|array  $columns
     * @return Blueprint
     */
    public function background($columns = null)
    {
        $columns = $this->fluent($columns);

        $this->index($columns, ['background' => true]);

        return $this;
    }

    /**
     * Specify a sparse index for the collection.
     *
     * @param  string|array  $columns
     * @param  array         $options
     * @return Blueprint
     */
    public function sparse($columns = null, $options = [])
    {
        $columns = $this->fluent($columns);

        $options['sparse'] = true;

        $this->index($columns, $options);

        return $this;
    }

    /**
     * Specify the number of seconds after wich a document should be considered expired based,
     * on the given single-field index containing a date.
     *
     * @param  string|array  $columns
     * @param  int           $seconds
     * @return Blueprint
     */
    public function expire($columns, $seconds)
    {
        $columns = $this->fluent($columns);

        $this->index($columns, ['expireAfterSeconds' => $seconds]);

        return $this;
    }

    /**
     * Indicate that the table needs to be created.
     *
     * @return bool
     */
    public function create()
    {
        $collection = $this->collection->getName();

        $db = $this->connection->getMongoDB();

        // Ensure the collection is created.
        $db->createCollection($collection);
    }

    /**
     * Indicate that the collection should be dropped.
     *
     * @return bool
     */
    public function drop()
    {
        $this->collection->drop();
    }

    /**
     * Add a new column to the blueprint.
     *
     * @param  string  $type
     * @param  string  $name
     * @param  array   $parameters
     * @return Blueprint
     */
    protected function addColumn($type, $name, array $parameters = [])
    {
        $this->fluent($name);

        return $this;
    }

    /**
     * Allow fluent columns.
     *
     * @param  string|array  $columns
     * @return string|array
     */
    protected function fluent($columns = null)
    {
        if (is_null($columns))
        {
            return $this->columns;
        }
        elseif (is_string($columns))
        {
            return $this->columns = [$columns];
        }
        else
        {
            return $this->columns = $columns;
        }
    }

    /**
     * Allows the use of unsupported schema methods.
     *
     * @return Blueprint
     */
    public function __call($method, $args)
    {
        // Dummy.
        return $this;
    }

}
