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
use function usort;

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
      /** @param string|null $schema Database name */
      public function getTables($schema = null)
      {
          $db = $this->connection->getDatabase($schema);
          $collections = [];
  
          foreach ($db->listCollections() as $collectionInfo) {
              $collectionName = $collectionInfo->getName();
  
              // Skip system collections
              if (str_starts_with($collectionName, 'system.')) {
                  continue;
              }
              // Skip views it doesnt suport aggregate
              $isView = ($collectionInfo['type'] ?? '') === 'view';
              $stats = null;
  
              if (! $isView) {
                  // Only run aggregation if it's a normal collection
                  $stats = $db->selectCollection($collectionName)->aggregate([
                      ['$collStats' => ['storageStats' => ['scale' => 1]]],
                      ['$project' => ['storageStats.totalSize' => 1]],
                  ])->toArray();
              }
  
              $collections[] = [
                  'name' => $collectionName,
                  'schema' => $db->getDatabaseName(),
                  'schema_qualified_name' => $db->getDatabaseName().'.'.$collectionName,
                  'size' => $stats[0]?->storageStats?->totalSize ?? null,
                  'comment' => null,
                  'collation' => null,
                  'engine' => $isView ? 'view' : 'collection',
              ];
          }
  
          usort($collections, fn ($a, $b) => $a['name'] <=> $b['name']);
  
          return $collections;
      }
  
      /**
       * @param  string|null  $schema
       * @param  bool  $schemaQualified  If a schema is provided, prefix the collection names with the schema name
       * @return array
       */
      public function getTableListing($schema = null, $schemaQualified = false)
      {
          $collections = [];
  
          if ($schema === null || is_string($schema)) {
              $collections[$schema ?? 0] = iterator_to_array($this->connection->getDatabase($schema)->listCollectionNames());
          } elseif (is_array($schema)) {
              foreach ($schema as $db) {
                  $collections[$db] = iterator_to_array($this->connection->getDatabase($db)->listCollectionNames());
              }
          }
  
          if ($schema && $schemaQualified) {
              $collections = array_map(fn ($db, $collections) => array_map(static fn ($collection) => $db.'.'.$collection, $collections), array_keys($collections), $collections);
          }
  
          $collections = array_merge(...array_values($collections));
  
          // Exclude system collections before sorting
          $collections = array_filter($collections, fn ($name) => ! str_starts_with($name, 'system.'));
  
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
            $name = $collection->getName();

            // Skip system collections
            if (str_starts_with($name, 'system.')) {
                continue;
            }

            $collections[] = $name;
        }

        return $collections;
    }
}
