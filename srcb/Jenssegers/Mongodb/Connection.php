<?php namespace Jenssegers\Mongodb;

class Connection {

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
     * The database connection configuration options.
     *
     * @var string
     */
    protected $config = array();

    /**
     * Create a new database connection instance.
     *
     * @param  array   $config
     * @return void
     */
    public function __construct(array $config)
    {
        if (!is_null($this->connection)) return;

        // Store configuration
        $this->config = $config;

        // Check for connection options
        $options = array_get($config, 'options', array());

        // Create connection
        $this->connection = new \MongoClient($this->getDsn($config), $options);

        // Select database
        $this->db = $this->connection->{$config['database']};

        return $this;
    }

    /**
     * Get a mongodb collection.
     *
     * @param  string   $name
     * @return MongoDB
     */
    public function getCollection($name)
    {
        return $this->db->{$name};
    }

    /**
     * Get the mongodb database object.
     *
     * @return  MongoDB
     */
    public function getDb()
    {
        return $this->db;
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

        $dsn = "mongodb://";

        if (isset($config['username']) and isset($config['password']))
        {
            $dsn .= "{$username}:{$password}@";
        }

        $dsn.= "{$host}";

        if (isset($config['port']))
        {
            $dsn .= ":{$port}";
        }

        $dsn.= "/{$database}";

        return $dsn;
    }

}