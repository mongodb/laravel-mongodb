<?php namespace Jenssegers\Mongodb;

use Illuminate\Database\ConnectionResolverInterface;
use Jenssegers\Mongodb\Connection;

class ConnectionResolver implements ConnectionResolverInterface {

	/**
	 * The application instance.
	 *
	 * @var Illuminate\Foundation\Application
	 */
	protected $app;

	/**
	 * All of the registered connections.
	 *
	 * @var array
	 */
	protected $connections = array();

	/**
	 * The default connection name.
	 *
	 * @var string
	 */
	protected $default = 'mongodb';

	/**
	 * Create a new database manager instance.
	 *
	 * @param  Illuminate\Foundation\Application  $app
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
	 * @return \Illuminate\Database\Connection
	 */
	public function connection($name = null)
	{
		if (is_null($name)) $name = $this->getDefaultConnection();

		// If we haven't created this connection, we'll create it based on the config
		// provided in the application.
		if (!isset($this->connections[$name]))
		{
			// Get connection configuration
			$connections = $this->app['config']['database']['connections'];

			if (is_null($config = array_get($connections, $name)))
			{
				throw new \InvalidArgumentException("MongoDB [$name] not configured.");
			}

			// Make connection
			$this->connections[$name] = $this->makeConnection($connections[$name]);
		}

		return $this->connections[$name];
	}

	/**
	 * Create a connection.
	 *
	 * @param  array  $config
	 */
	public function makeConnection($config) {
		return new Connection($config);
	}

	/**
	 * Get the default connection name.
	 *
	 * @return string
	 */
	public function getDefaultConnection()
	{
		return $this->default;
	}

	/**
	 * Set the default connection name.
	 *
	 * @param  string  $name
	 * @return void
	 */
	public function setDefaultConnection($name)
	{
		$this->default = $name;
	}

}