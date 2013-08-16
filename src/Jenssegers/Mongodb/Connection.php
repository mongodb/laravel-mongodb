<?php namespace Jenssegers\Mongodb;

use Jenssegers\Mongodb\Builder as QueryBuilder;
use MongoClient;

class Connection extends \Illuminate\Database\Connection {

    /**
     * The MongoDB database handler.
     *
     * @var resource
     */
    protected $db;

    /**
     * The MongoClient connection handler.
     *
     * @var resource
     */
    protected $connection;

    /**
     * Create a new database connection instance.
     *
     * @param  array   $config
     * @return void
     */
    public function __construct(array $config)
    {
        // Store configuration
        $this->config = $config;

        // Check for connection options
        $options = array_get($config, 'options', array());

        // Create connection
        $this->connection = new MongoClient($this->getDsn($config), $options);

        // Select database
        $this->db = $this->connection->selectDB($config['database']);
    }

    /**
     * Return a new QueryBuilder for a collection
     *
     * @param  string  $collection
     * @return QueryBuilder
     */
    public function collection($collection)
    {
        $query = new QueryBuilder($this);

        return $query->from($collection);
    }

    /**
     * Begin a fluent query against a database table.
     *
     * @param  string  $table
     * @return QueryBuilder
     */
    public function table($table)
    {
        return $this->collection($table);
    }

    /**
     * Get a MongoDB collection.
     *
     * @param  string   $name
     * @return MongoDB
     */
    public function getCollection($name)
    {
        return $this->db->{$name};
    }

    /**
     * Get the MongoDB database object.
     *
     * @return  MongoDB
     */
    public function getMongoDB()
    {
        return $this->db;
    }

    /**
     * return MongoClient object
     *
     * @return MongoClient
     */
    public function getMongoClient()
    {
        return $this->connection;
    }

    /**
     * Create a DSN string from a configuration.
     *
     * @param  array   $config
     * @return string
     */
    protected function getDsn(array $config)
    {
        // First we will create the basic DSN setup as well as the port if it is in
        // in the configuration options. This will give us the basic DSN we will
        // need to establish the MongoClient and return them back for use.
        extract($config);

        // Treat host option as array of hosts
        $hosts = is_array($config['host']) ? $config['host'] : array($config['host']);

        // Add ports to hosts
        foreach ($hosts as &$host)
        {
            if (isset($config['port']))
            {
                $host = "{$host}:{$port}";
            }
        }

        // Credentials
        if (isset($config['username']) and isset($config['password']))
        {
            $credentials = "{$username}:{$password}@";
        }
        else
        {
            $credentials = '';
        }

        return "mongodb://{$credentials}" . implode(',', $hosts);
    }

    /**
     * Dynamically pass methods to the connection.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return call_user_func_array(array($this->db, $method), $parameters);
    }

}