<?php

namespace Jenssegers\Mongodb\Schema;

use Closure;
use Jenssegers\Mongodb\Connection;

class Builder extends \Illuminate\Database\Schema\Builder
{
    /**
     * @inheritdoc
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @inheritdoc
     */
    public function hasColumn($table, $column)
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function hasColumns($table, array $columns)
    {
        return true;
    }

    /**
     * Determine if the given collection exists.
     *
     * @param  string $collection
     * @return bool
     */
    public function hasCollection($collection)
    {
        $db = $this->connection->getMongoDB();

        foreach ($db->listCollections() as $collectionFromMongo) {
            if ($collectionFromMongo->getName() == $collection) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function hasTable($collection)
    {
        return $this->hasCollection($collection);
    }

    /**
     * Modify a collection on the schema.
     *
     * @param  string $collection
     * @param  Closure $callback
     * @return bool
     */
    public function collection($collection, Closure $callback)
    {
        $blueprint = $this->createBlueprint($collection);

        if ($callback) {
            $callback($blueprint);
        }
    }

    /**
     * @inheritdoc
     */
    public function table($collection, Closure $callback)
    {
        return $this->collection($collection, $callback);
    }

    /**
     * @inheritdoc
     */
    public function create($collection, Closure $callback = null, array $options = [])
    {
        $blueprint = $this->createBlueprint($collection);

        $blueprint->create($options);

        if ($callback) {
            $callback($blueprint);
        }
    }

    /**
     * @inheritdoc
     */
    public function dropIfExists($collection)
    {
        if ($this->hasCollection($collection)) {
            return $this->drop($collection);
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function drop($collection)
    {
        $blueprint = $this->createBlueprint($collection);

        return $blueprint->drop();
    }

    /**
     * @inheritdoc
     */
    public function dropAllTables()
    {
        foreach ($this->getAllCollections() as $collection) {
            $this->drop($collection);
        }
    }

    /**
     * @inheritdoc
     */
    protected function createBlueprint($collection, Closure $callback = null)
    {
        return new Blueprint($this->connection, $collection);
    }

    /**
     * Get all of the collections names for the database.
     *
     * @return array
     */
    protected function getAllCollections()
    {
        $collections = [];
        foreach ($this->connection->getMongoDB()->listCollections() as $collection) {
            $collections[] = $collection->getName();
        }

        return $collections;
    }
}
