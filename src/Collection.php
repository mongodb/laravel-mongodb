<?php

namespace Jenssegers\Mongodb;

use Exception;
use MongoDB\BSON\ObjectID;
use MongoDB\Collection as MongoCollection;

class Collection
{
    /**
     * The connection instance.
     *
     * @var Connection
     */
    protected $connection;

    /**
     * The MongoCollection instance.
     *
     * @var MongoCollection
     */
    protected $collection;

    /**
     * @param  Connection  $connection
     * @param  MongoCollection  $collection
     */
    public function __construct(Connection $connection, MongoCollection $collection)
    {
        $this->connection = $connection;
        $this->collection = $collection;
    }

    /**
     * Handle dynamic method calls.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        $start = microtime(true);
        $result = $this->collection->$method(...$parameters);

        // Once we have run the query we will calculate the time that it took to run and
        // then log the query, bindings, and execution time so we will report them on
        // the event that the developer needs them. We'll log time in milliseconds.
        $time = $this->connection->getElapsedTime($start);

        $query = [];

        // Convert the query parameters to a json string.
        array_walk_recursive($parameters, function (&$item, $key) {
            if ($item instanceof ObjectID) {
                $item = (string) $item;
            }
        });

        // Convert the query parameters to a json string.
        foreach ($parameters as $parameter) {
            try {
                $query[] = json_encode($parameter);
            } catch (Exception $e) {
                $query[] = '{...}';
            }
        }

        $queryString = $this->collection->getCollectionName().'.'.$method.'('.implode(',', $query).')';

        $this->connection->logQuery($queryString, [], $time);

        return $result;
    }
}
