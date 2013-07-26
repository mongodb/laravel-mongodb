<?php namespace Jenssegers\Mongodb;

use Illuminate\Database\ConnectionResolverInterface;
use Jenssegers\Mongodb\Connection;
use Jenssegers\Mongodb\Builder as QueryBuilder;

class DatabaseManager implements ConnectionResolverInterface {

	/**
	 * The application instance.
	 *
	 * @var \Illuminate\Foundation\Application
	 */
	protected $app;

	/**
	 * The active connection instances.
	 *
	 * @var array
	 */
	protected $connections = array();

	/**
	 * The custom connection resolvers.
	 *
	 * @var array
	 */
	protected $extensions = array();

	/**
	 * The default database connection.
	 *
	 * @var array
	 */
	protected $defaultConnection = 'mongodb';

	/**
	 * Create a new database manager instance.
	 *
	 * @param  \Illuminate\Foundation\Application  $app
	 * @return void
	 */
	public function __construct($app)
	{
		$this->app = $app;
	}

	/**
	 * Get a database connection instance.
	 *
	 * @param  string  $name
	 * @return Connection
	 */
	public function connection($name = null)
	{
		$name = $name ?: $this->getDefaultConnection();

		// If we haven't created this connection, we'll create it based on the config
		// provided in the application. Once we've created the connections we will
		// set the "fetch mode" for PDO which determines the query return types.
		if ( ! isset($this->connections[$name]))
		{
			$connection = $this->makeConnection($name);

			$this->connections[$name] = $this->prepare($connection);
		}

		return $this->connections[$name];
	}

	/**
	 * Reconnect to the given database.
	 *
	 * @param  string  $name
	 * @return Connection
	 */
	public function reconnect($name = null)
	{
		unset($this->connections[$name]);

		return $this->connection($name);
	}

	/**
	 * Make the database connection instance.
	 *
	 * @param  string  $name
	 * @return Connection
	 */
	protected function makeConnection($name)
	{
		$config = $this->getConfig($name);

		if (isset($this->extensions[$name]))
		{
			return call_user_func($this->extensions[$name], $config);
		}

		return new Connection($config);
	}

	/**
	 * Prepare the database connection instance.
	 *
	 * @param  Connection  $connection
	 * @return Connection
	 */
	protected function prepare(Connection $connection)
	{
		$connection->setEventDispatcher($this->app['events']);

		// The database connection can also utilize a cache manager instance when cache
		// functionality is used on queries, which provides an expressive interface
		// to caching both fluent queries and Eloquent queries that are executed.
		$app = $this->app;

		$connection->setCacheManager(function() use ($app)
		{
			return $app['cache'];
		});

		// We will setup a Closure to resolve the paginator instance on the connection
		// since the Paginator isn't sued on every request and needs quite a few of
		// our dependencies. It'll be more efficient to lazily resolve instances.
		$connection->setPaginator(function() use ($app)
		{
			return $app['paginator'];
		});

		return $connection;
	}

	/**
	 * Get the configuration for a connection.
	 *
	 * @param  string  $name
	 * @return array
	 */
	protected function getConfig($name)
	{
		$name = $name ?: $this->getDefaultConnection();

		// To get the database connection configuration, we will just pull each of the
		// connection configurations and get the configurations for the given name.
		// If the configuration doesn't exist, we'll throw an exception and bail.
		$connections = $this->app['config']['database.connections'];

		if (is_null($config = array_get($connections, $name)))
		{
			throw new \InvalidArgumentException("Database [$name] not configured.");
		}

		return $config;
	}

	/**
	 * Get the default connection name.
	 *
	 * @return string
	 */
	public function getDefaultConnection()
	{
		return $this->defaultConnection;
	}

	/**
	 * Set the default connection name.
	 *
	 * @param  string  $name
	 * @return void
	 */
	public function setDefaultConnection($name)
	{
		$this->defaultConnection = $name;
	}

	/**
	 * Dynamically pass methods to the default connection.
	 *
	 * @param  string  $method
	 * @param  array   $parameters
	 * @return mixed
	 */
	public function __call($method, $parameters)
	{
		return call_user_func_array(array($this->connection(), $method), $parameters);
	}

}