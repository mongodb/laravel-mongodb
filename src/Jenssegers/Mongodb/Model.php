<?php namespace Jenssegers\Mongodb;

use Illuminate\Database\ConnectionResolverInterface as Resolver;
use Illuminate\Database\Eloquent\Collection;

abstract class Model extends \ArrayObject {

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection;

    /**
     * The collection associated with the model.
     *
     * @var string
     */
    protected $collection;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = '_id';

    /**
     * The connection resolver instance.
     *
     * @var Jenssegers\Mongodb\ConnectionResolverInterface
     */
    protected static $resolver;



    /**
     * Get properties from internal array
     *
     * @param  string $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this[$name];
    }

    /**
     * Write all properties to internal array
     *
     * @param  string $name
     * @param  mixed  $value
     */
    public function __set($name, $value)
    {
        $this[$name] = $value;
    }

    /**
     * Handle dynamic method calls into the method.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        // Create a query
        $query = $this->newQuery();

        return call_user_func_array(array($query, $method), $parameters);
    }

    /**
     * Handle dynamic static method calls into the method.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        $instance = new static;

        return call_user_func_array(array($instance, $method), $parameters);
    }

    /**
     * Get all of the models from the database.
     *
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function all($columns = array('*'))
    {
        $instance = new static;

        return $instance->newQuery()->get($columns);
    }

    /**
     * Find a model by its primary key.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return \Jenssegers\Mongodb\Model|\Illuminate\Database\Eloquent\Collection
     */
    public static function find($id, $columns = array('*'))
    {
        $instance = new static;

        if (is_array($id))
        {
            $id = array_map(function($value)
            {
                return ($value instanceof MongoID) ? $value : new MongoID($value);
            }, $id);

            return $instance->newQuery()->whereIn($instance->getKeyName(), $id)->get($columns);
        }

        return $instance->newQuery()->find($id, $columns);
    }

    /**
     * Create a new instance of the given model.
     *
     * @param  array  $attributes
     * @param  bool   $exists
     * @return \Jenssegers\Mongodb\Model
     */
    public function newInstance($attributes = array(), $exists = false)
    {
        // This method just provides a convenient way for us to generate fresh model
        // instances of this current model. It is particularly useful during the
        // hydration of new objects via the Eloquent query builder instances.
        $model = new static((array) $attributes);

        $model->exists = $exists;

        return $model;
    }

    /**
     * Get a new query for the model's table.
     *
     * @return \Jenssegers\Mongodb\Query
     */
    public function newQuery()
    {
        $query = new Query($this);

        return $query;
    }

    /**
     * Create a new Collection instance.
     *
     * @param  array  $models
     * @return LMongo\Eloquent\Collection
     */
    public function newCollection(array $models = array())
    {
        return new Collection($models);
    }

    /**
     * Get the database collection for the model.
     *
     * @return \Jenssegers\Mongodb\Connection
     */
    public function getCollection()
    {
        return $this->collection;
    }

    /**
     * Get the database connection for the model.
     *
     * @return \Jenssegers\Mongodb\Connection
     */
    public function getConnection()
    {
        return static::resolveConnection($this->connection);
    }

    /**
     * Get the current connection name for the model.
     *
     * @return string
     */
    public function getConnectionName()
    {
        return $this->connection;
    }

    /**
     * Set the connection associated with the model.
     *
     * @param  string  $name
     * @return void
     */
    public function setConnection($name)
    {
        $this->connection = $name;
    }

    /**
     * Get the primary key for the model.
     *
     * @return string
     */
    public function getKeyName()
    {
        return $this->primaryKey;
    }

    /**
     * Resolve a connection instance by name.
     *
     * @param  string  $connection
     * @return \Jenssegers\Mongodb\Connection
     */
    public static function resolveConnection($connection)
    {
        return static::$resolver->connection($connection);
    }

    /**
     * Get the connection resolver instance.
     *
     * @return \Jenssegers\Mongodb\ConnectionResolverInterface
     */
    public static function getConnectionResolver()
    {
        return static::$resolver;
    }

    /**
     * Set the connection resolver instance.
     *
     * @param  Jenssegers\Mongodb\ConnectionResolverInterface  $resolver
     * @return void
     */
    public static function setConnectionResolver(Resolver $resolver)
    {
        static::$resolver = $resolver;
    }

}