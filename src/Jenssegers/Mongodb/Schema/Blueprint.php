<?php

namespace Jenssegers\Mongodb\Schema;

use Illuminate\Database\Connection;

class Blueprint extends \Illuminate\Database\Schema\Blueprint
{
    /**
     * The MongoConnection object for this blueprint.
     *
     * @var \Jenssegers\Mongodb\Connection
     */
    protected $connection;

    /**
     * The MongoCollection object for this blueprint.
     *
     * @var \Jenssegers\Mongodb\Collection|\MongoDB\Collection
     */
    protected $collection;

    /**
     * Fluent columns.
     *
     * @var array
     */
    protected $columns = [];

    /**
     * @inheritdoc
     */
    public function __construct(Connection $connection, $collection)
    {
        $this->connection = $connection;

        $this->collection = $this->connection->getCollection($collection);
    }

    /**
     * @inheritdoc
     */
    public function index($columns = null, $name = null, $algorithm = null, $options = [])
    {
        $columns = $this->fluent($columns);

        // Columns are passed as a default array.
        if (is_array($columns) && is_int(key($columns))) {
            // Transform the columns to the required array format.
            $transform = [];

            foreach ($columns as $column) {
                $transform[$column] = 1;
            }

            $columns = $transform;
        }

        if ($name !== null) {
            $options['name'] = $name;
        }

        $this->collection->createIndex($columns, $options);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function primary($columns = null, $name = null, $algorithm = null, $options = [])
    {
        return $this->unique($columns, $name, $algorithm, $options);
    }

    /**
     * @inheritdoc
     */
    public function dropIndex($indexOrColumns = null)
    {
        if (is_array($indexOrColumns)) {
            $indexOrColumns = $this->fluent($indexOrColumns);

            // Transform the columns to the index name.
            $transform = [];

            foreach ($indexOrColumns as $column) {
                $transform[$column] = $column . '_1';
            }

            $indexOrColumns = join('_', $transform);
        }

        $this->collection->dropIndex($indexOrColumns);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function unique($columns = null, $name = null, $algorithm = null, $options = [])
    {
        $columns = $this->fluent($columns);

        $options['unique'] = true;

        $this->index($columns, $name, $algorithm, $options);

        return $this;
    }

    /**
     * Specify a non blocking index for the collection.
     *
     * @param  string|array $columns
     * @return Blueprint
     */
    public function background($columns = null)
    {
        $columns = $this->fluent($columns);

        $this->index($columns, null, null, ['background' => true]);

        return $this;
    }

    /**
     * Specify a sparse index for the collection.
     *
     * @param  string|array $columns
     * @param  array $options
     * @return Blueprint
     */
    public function sparse($columns = null, $options = [])
    {
        $columns = $this->fluent($columns);

        $options['sparse'] = true;

        $this->index($columns, null, null, $options);

        return $this;
    }

    /**
     * Specify a geospatial index for the collection.
     *
     * @param  string|array $columns
     * @param  string $index
     * @param  array $options
     * @return Blueprint
     */
    public function geospatial($columns = null, $index = '2d', $options = [])
    {
        if ($index == '2d' || $index == '2dsphere') {
            $columns = $this->fluent($columns);

            $columns = array_flip($columns);

            foreach ($columns as $column => $value) {
                $columns[$column] = $index;
            }

            $this->index($columns, null, null, $options);
        }

        return $this;
    }

    /**
     * Specify the number of seconds after wich a document should be considered expired based,
     * on the given single-field index containing a date.
     *
     * @param  string|array $columns
     * @param  int $seconds
     * @return Blueprint
     */
    public function expire($columns, $seconds)
    {
        $columns = $this->fluent($columns);

        $this->index($columns, null, null, ['expireAfterSeconds' => $seconds]);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function create()
    {
        $collection = $this->collection->getCollectionName();

        $db = $this->connection->getMongoDB();

        // Ensure the collection is created.
        $db->createCollection($collection);
    }

    /**
     * @inheritdoc
     */
    public function drop()
    {
        $this->collection->drop();
    }

    /**
     * @inheritdoc
     */
    public function addColumn($type, $name, array $parameters = [])
    {
        $this->fluent($name);

        return $this;
    }

    /**
     * Specify a sparse and unique index for the collection.
     *
     * @param  string|array $columns
     * @param  array $options
     * @return Blueprint
     */
    public function sparse_and_unique($columns = null, $options = [])
    {
        $columns = $this->fluent($columns);

        $options['sparse'] = true;
        $options['unique'] = true;

        $this->index($columns, null, null, $options);

        return $this;
    }

    /**
     * Allow fluent columns.
     *
     * @param  string|array $columns
     * @return string|array
     */
    protected function fluent($columns = null)
    {
        if (is_null($columns)) {
            return $this->columns;
        } elseif (is_string($columns)) {
            return $this->columns = [$columns];
        } else {
            return $this->columns = $columns;
        }
    }

    /**
     * Allows the use of unsupported schema methods.
     *
     * @param $method
     * @param $args
     * @return Blueprint
     */
    public function __call($method, $args)
    {
        // Dummy.
        return $this;
    }
}
