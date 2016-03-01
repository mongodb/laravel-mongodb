<?php
namespace Jenssegers\Mongodb\Contracts;

use Jenssegers\Mongodb\Collection;
use Jenssegers\Mongodb\Query;
use Jenssegers\Mongodb\Schema;

interface ConnectionContract
{
    /**
     * Begin a fluent query against a database collection.
     *
     * @param  string $collection
     * @return Query\Builder
     */
    public function collection($collection);

    /**
     * Begin a fluent query against a database collection.
     *
     * @param  string $table
     * @return Query\Builder
     */
    public function table($table);

    /**
     * Get a MongoDB collection.
     *
     * @param  string $name
     * @return Collection
     */
    public function getCollection($name);

    /**
     * Get a schema builder instance for the connection.
     *
     * @return Schema\Builder
     */
    public function getSchemaBuilder();

    /**
     * Get the MongoDB database object.
     *
     * @return \MongoDB\Database
     */
    public function getMongoDB();

    /**
     * return MongoDB object.
     *
     * @return \MongoDB\Client
     */
    public function getMongoClient();

    /**
     * Disconnect from the underlying MongoDB connection.
     */
    public function disconnect();

    /**
     * Get the elapsed time since a given starting point.
     *
     * @param  int $start
     * @return float
     */
    public function getElapsedTime($start);

    /**
     * Get the PDO driver name.
     *
     * @return string
     */
    public function getDriverName();
}
