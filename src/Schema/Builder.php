<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Schema;

use Closure;
use MongoDB\Model\CollectionInfo;

use function count;
use function current;
use function iterator_to_array;
use function sort;
use function usort;

class Builder extends \Illuminate\Database\Schema\Builder
{
    /** @inheritdoc */
    public function hasColumn($table, $column)
    {
        return true;
    }

    /** @inheritdoc */
    public function hasColumns($table, array $columns)
    {
        return true;
    }

    /**
     * Determine if the given collection exists.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasCollection($name)
    {
        $db = $this->connection->getMongoDB();

        $collections = iterator_to_array($db->listCollections([
            'filter' => ['name' => $name],
        ]), false);

        return count($collections) !== 0;
    }

    /** @inheritdoc */
    public function hasTable($table)
    {
        return $this->hasCollection($table);
    }

    /**
     * Modify a collection on the schema.
     *
     * @param string $collection
     *
     * @return void
     */
    public function collection($collection, Closure $callback)
    {
        $blueprint = $this->createBlueprint($collection);

        if ($callback) {
            $callback($blueprint);
        }
    }

    /** @inheritdoc */
    public function table($table, Closure $callback)
    {
        $this->collection($table, $callback);
    }

    /** @inheritdoc */
    public function create($table, ?Closure $callback = null, array $options = [])
    {
        $blueprint = $this->createBlueprint($table);

        $blueprint->create($options);

        if ($callback) {
            $callback($blueprint);
        }
    }

    /** @inheritdoc */
    public function dropIfExists($table)
    {
        if ($this->hasCollection($table)) {
            $this->drop($table);
        }
    }

    /** @inheritdoc */
    public function drop($table)
    {
        $blueprint = $this->createBlueprint($table);

        $blueprint->drop();
    }

    /** @inheritdoc */
    public function dropAllTables()
    {
        foreach ($this->getAllCollections() as $collection) {
            $this->drop($collection);
        }
    }

    public function getTables()
    {
        $db = $this->connection->getMongoDB();
        $collections = [];

        foreach ($db->listCollectionNames() as $collectionName) {
            $stats = $db->selectCollection($collectionName)->aggregate([
                ['$collStats' => ['storageStats' => ['scale' => 1]]],
                ['$project' => ['storageStats.totalSize' => 1]],
            ])->toArray();

            $collections[] = [
                'name' => $collectionName,
                'schema' => null,
                'size' => $stats[0]?->storageStats?->totalSize ?? null,
                'comment' => null,
                'collation' => null,
                'engine' => null,
            ];
        }

        usort($collections, function ($a, $b) {
            return $a['name'] <=> $b['name'];
        });

        return $collections;
    }

    public function getTableListing()
    {
        $collections = iterator_to_array($this->connection->getMongoDB()->listCollectionNames());

        sort($collections);

        return $collections;
    }

    /** @inheritdoc */
    protected function createBlueprint($table, ?Closure $callback = null)
    {
        return new Blueprint($this->connection, $table);
    }

    /**
     * Get collection.
     *
     * @param string $name
     *
     * @return bool|CollectionInfo
     */
    public function getCollection($name)
    {
        $db = $this->connection->getMongoDB();

        $collections = iterator_to_array($db->listCollections([
            'filter' => ['name' => $name],
        ]), false);

        return count($collections) ? current($collections) : false;
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
