<?php

namespace Jenssegers\Mongodb;

use Exception;
use MongoDB\BSON\ObjectID;
use MongoDB\Collection as MongoCollection;
use Illuminate\Contracts\Support\Arrayable;

class Collection
{
    /**
     * The connection instance.
     *
     * @var Connection
     */
    protected $connection;

    /**
     * The MongoCollection instance..
     *
     * @var MongoCollection
     */
    protected $collection;

    /**
     * @param Connection      $connection
     * @param MongoCollection $collection
     */
    public function __construct(Connection $connection, MongoCollection $collection)
    {
        $this->connection = $connection;
        $this->collection = $collection;
    }

    private function parametersToArray($parameters)
    {
        if (!is_array($parameters)) {
            if ($parameters instanceof Arrayable) {
                return $parameters->toArray();
            }
            return $parameters;
        }
        $result = [];
        foreach ($parameters as $k => $v) {
            $result[$k] = $this->parametersToArray($v);
        }
        return $result;
    }

    private function parametersToLogArray($parameters)
    {
        if (!is_array($parameters)) {
            if ($parameters instanceof ObjectID) {
                return (string) $parameters;
            }
            return $parameters;
        }
        $result = [];
        foreach ($parameters as $k => $v) {
            $result[$k] = $this->parametersToLogArray($v);
        }
        return $result;
    }

    /**
     * Handle dynamic method calls.
     *
     * @param  string $method
     * @param  array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $start = microtime(true);
        $parameters = $this->parametersToArray($parameters);
        $result = call_user_func_array([$this->collection, $method], $parameters);

        if ($this->connection->logging()) {
            // Once we have run the query we will calculate the time that it took to run and
            // then log the query, bindings, and execution time so we will report them on
            // the event that the developer needs them. We'll log time in milliseconds.
            $time = $this->connection->getElapsedTime($start);

            $query = [];

            // Convert the query parameters to a json string.
            $parameters = $this->parametersToLogArray($parameters);

            // Convert the query parameters to a json string.
            foreach ($parameters as $parameter) {
                try {
                    $query[] = json_encode($parameter);
                } catch (Exception $e) {
                    $query[] = '{...}';
                }
            }

            $queryString = $this->collection->getCollectionName() . '.' . $method . '(' . implode(',', $query) . ')';

            $this->connection->logQuery($queryString, [], $time);
        }

        return $result;
    }
}
