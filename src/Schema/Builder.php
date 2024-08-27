<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Schema;

use Closure;
use MongoDB\Model\CollectionInfo;
use MongoDB\Model\IndexInfo;

use function array_fill_keys;
use function array_keys;
use function assert;
use function count;
use function current;
use function implode;
use function iterator_to_array;
use function sort;
use function sprintf;
use function trigger_error;
use function usort;

use const E_USER_DEPRECATED;

class Builder extends \Illuminate\Database\Schema\Builder
{
    /**
     * Check if column exists in the collection schema.
     *
     * @param string $table
     * @param string $column
     */
    public function hasColumn($table, $column): bool
    {
        return $this->hasColumns($table, [$column]);
    }

    /**
     * Check if columns exists in the collection schema.
     *
     * @param string   $table
     * @param string[] $columns
     */
    public function hasColumns($table, array $columns): bool
    {
        $collection = $this->connection->table($table);

        return $collection
            ->where(array_fill_keys($columns, ['$exists' => true]))
            ->project(['_id' => 1])
            ->exists();
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
     * @deprecated since mongodb/laravel-mongodb 4.8, use the function table() instead
     *
     * @param string $collection
     *
     * @return void
     */
    public function collection($collection, Closure $callback)
    {
        @trigger_error('Since mongodb/laravel-mongodb 4.8, the method Schema\Builder::collection() is deprecated and will be removed in version 5.0. Use the function table() instead.', E_USER_DEPRECATED);

        $this->table($collection, $callback);
    }

    /** @inheritdoc */
    public function table($table, Closure $callback)
    {
        $blueprint = $this->createBlueprint($table);

        if ($callback) {
            $callback($blueprint);
        }
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

    public function getColumns($table)
    {
        $stats = $this->connection->getMongoDB()->selectCollection($table)->aggregate([
            // Sample 1,000 documents to get a representative sample of the collection
            ['$sample' => ['size' => 1_000]],
            // Convert each document to an array of fields
            ['$project' => ['fields' => ['$objectToArray' => '$$ROOT']]],
            // Unwind to get one document per field
            ['$unwind' => '$fields'],
            // Group by field name, count the number of occurrences and get the types
            [
                '$group' => [
                    '_id' => '$fields.k',
                    'total' => ['$sum' => 1],
                    'types' => ['$addToSet' => ['$type' => '$fields.v']],
                ],
            ],
            // Get the most seen field names
            ['$sort' => ['total' => -1]],
            // Limit to 1,000 fields
            ['$limit' => 1_000],
            // Sort by field name
            ['$sort' => ['_id' => 1]],
        ], [
            'typeMap' => ['array' => 'array'],
            'allowDiskUse' => true,
        ])->toArray();

        $columns = [];
        foreach ($stats as $stat) {
            sort($stat->types);
            $type = implode(', ', $stat->types);
            $columns[] = [
                'name' => $stat->_id,
                'type_name' => $type,
                'type' => $type,
                'collation' => null,
                'nullable' => $stat->_id !== '_id',
                'default' => null,
                'auto_increment' => false,
                'comment' => sprintf('%d occurrences', $stat->total),
                'generation' => $stat->_id === '_id' ? ['type' => 'objectId', 'expression' => null] : null,
            ];
        }

        return $columns;
    }

    public function getIndexes($table)
    {
        $indexes = $this->connection->getMongoDB()->selectCollection($table)->listIndexes();

        $indexList = [];
        foreach ($indexes as $index) {
            assert($index instanceof IndexInfo);
            $indexList[] = [
                'name' => $index->getName(),
                'columns' => array_keys($index->getKey()),
                'primary' => $index->getKey() === ['_id' => 1],
                'type' => match (true) {
                    $index->isText() => 'text',
                    $index->is2dSphere() => '2dsphere',
                    $index->isTtl() => 'ttl',
                    default => 'default',
                },
                'unique' => $index->isUnique(),
            ];
        }

        return $indexList;
    }

    public function getForeignKeys($table)
    {
        return [];
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
