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
        $query = array();

        // Build the query string.
        foreach ($parameters as $parameter)
        {
            try
            {
                $query[] = json_encode($parameter);
            }
            catch (Exception $e)
            {
                $query[] = '{...}';
            }
        }

        $start = microtime(true);

        //$result = call_user_func_array(array($this->collection, $method), $parameters);

        // Fix for PHP7
        // https://github.com/LearningLocker/learninglocker/issues/893#issue-203456708
        // based on https://github.com/alcaeus/mongo-php-adapter/issues/107#issuecomment-219393254
        if (PHP_VERSION_ID >= 70000 && in_array($method, array('insert', 'batchInsert', 'save'))) {
            $saveData = array_shift($parameters);
            $saveParams = array_shift($parameters);
            if (NULL==$saveParams) {
                $saveParams = array();
            }
            $result = call_user_func_array(array($this->collection, $method), array(&$saveData, $saveParams));
        } else {
            $result = call_user_func_array(array($this->collection, $method), $parameters);
        }


        // Once we have run the query we will calculate the time that it took to run and
        // then log the query, bindings, and execution time so we will report them on
        // the event that the developer needs them. We'll log time in milliseconds.
        $time = $this->connection->getElapsedTime($start);

        // Convert the query to a readable string.
        $queryString = $this->collection->getName() . '.' . $method . '(' . join(',', $query) . ')';

        $this->connection->logQuery($queryString, array(), $time);

        return $result;
    }

}
