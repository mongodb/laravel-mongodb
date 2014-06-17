<?php namespace Jenssegers\Mongodb;

use Exception;
use MongoCollection;
use Jenssegers\Mongodb\Connection;

class Collection {

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
     * Constructor.
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
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        // Build the query string.
        $query = $parameters;
        foreach ($query as &$param)
        {
            try
            {
                $param = json_encode($param);
            }
            catch (Exception $e)
            {
                $param = '{...}';
            }
        }

        $start = microtime(true);

        // Execute the query.
        $result = call_user_func_array(array($this->collection, $method), $parameters);

        // Log the query.
        $this->connection->logQuery(
            $this->collection->getName() . '.' . $method . '(' . join(',', $query) . ')',
            array(), $this->connection->getElapsedTime($start));

        return $result;
    }

}
