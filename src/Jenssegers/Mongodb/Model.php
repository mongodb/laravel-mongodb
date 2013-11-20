<?php namespace Jenssegers\Mongodb;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Jenssegers\Mongodb\DatabaseManager as Resolver;
use Jenssegers\Mongodb\Builder as QueryBuilder;
use Jenssegers\Mongodb\Relations\BelongsTo;
use Jenssegers\Mongodb\Relations\BelongsToMany;

use Carbon\Carbon;
use DateTime;
use MongoId;
use MongoDate;

abstract class Model extends \Illuminate\Database\Eloquent\Model {

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
     * @var \Illuminate\Database\ConnectionResolverInterface
     */
    protected static $resolver;

    /**
     * Convert a DateTime to a storable MongoDate object.
     *
     * @param  DateTime|int  $value
     * @return MongoDate
     */
    public function fromDateTime($value)
    {
        // Convert DateTime to MongoDate
        if ($value instanceof DateTime)
        {
            $value = new MongoDate($value->getTimestamp());
        }

        // Convert timestamp to MongoDate
        elseif (is_numeric($value))
        {
            $value = new MongoDate($value);
        }

        return $value;
    }

    /**
     * Return a timestamp as DateTime object.
     *
     * @param  mixed  $value
     * @return DateTime
     */
    protected function asDateTime($value)
    {
        // Convert timestamp
        if (is_numeric($value))
        {
            return Carbon::createFromTimestamp($value);
        }

        // Convert string
        if (is_string($value))
        {
            return new Carbon($value);
        }

        // Convert MongoDate
        if ($value instanceof MongoDate)
        {
            return Carbon::createFromTimestamp($value->sec);
        }

        return Carbon::instance($value);
    }

    /**
     * Get a fresh timestamp for the model.
     *
     * @return MongoDate
     */
    public function freshTimestamp()
    {
        return new MongoDate;
    }

    /**
     * Get the fully qualified "deleted at" column.
     *
     * @return string
     */
    public function getQualifiedDeletedAtColumn()
    {
        return $this->getDeletedAtColumn();
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        if (isset($this->collection)) return $this->collection;

        return parent::getTable();
    }

    /**
    * Define a one-to-one relationship.
    *
    * @param  string  $related
    * @param  string  $foreignKey
    * @param  string  $localKey
    * @return \Illuminate\Database\Eloquent\Relations\HasOne
    */
    public function hasOne($related, $foreignKey = null, $localKey = null)
    {
        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $instance = new $related;

        $localKey = $localKey ?: $this->getKeyName();

        return new HasOne($instance->newQuery(), $this, $foreignKey, $localKey);
    }

    /**
    * Define a one-to-many relationship.
    *
    * @param  string  $related
    * @param  string  $foreignKey
    * @param  string  $localKey
    * @return \Illuminate\Database\Eloquent\Relations\HasMany
    */
    public function hasMany($related, $foreignKey = null, $localKey = null)
    {
        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $instance = new $related;

        $localKey = $localKey ?: $this->getKeyName();

        return new HasMany($instance->newQuery(), $this, $foreignKey, $localKey);
    }

    /**
    * Define an inverse one-to-one or many relationship.
    *
    * @param  string  $related
    * @param  string  $foreignKey
    * @param  string  $otherKey
    * @param  string  $relation
    * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
    */
    public function belongsTo($related, $foreignKey = null, $otherKey = null, $relation = null)
    {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relatinoships.
        if (is_null($relation))
        {
            list(, $caller) = debug_backtrace(false);

            $relation = $caller['function'];
        }

        // If no foreign key was supplied, we can use a backtrace to guess the proper
        // foreign key name by using the name of the relationship function, which
        // when combined with an "_id" should conventionally match the columns.
        if (is_null($foreignKey))
        {
            $foreignKey = snake_case($relation).'_id';
        }

        $instance = new $related;

        // Once we have the foreign key names, we'll just create a new Eloquent query
        // for the related models and returns the relationship instance which will
        // actually be responsible for retrieving and hydrating every relations.
        $query = $instance->newQuery();

        $otherKey = $otherKey ?: $instance->getKeyName();

        return new BelongsTo($query, $this, $foreignKey, $otherKey, $relation);
    }

	/**
	 * Define a many-to-many relationship.
	 *
	 * @param  string  $related
	 * @param  string  $table
	 * @param  string  $foreignKey
	 * @param  string  $otherKey
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
	 */
	public function belongsToMany($related, $collection = null, $foreignKey = null, $otherKey = null)
	{
		$caller = $this->getBelongsToManyCaller();
		
		// First, we'll need to determine the foreign key and "other key" for the
		// relationship. Once we have determined the keys we'll make the query
		// instances as well as the relationship instances we need for this.
		$foreignKey = $foreignKey ?: $this->getForeignKey() . 's';

		$instance = new $related;
		
		$otherKey = $otherKey ?: $instance->getForeignKey() . 's';
		// If no table name was provided, we can guess it by concatenating the two
		// models using underscores in alphabetical order. The two model names
		// are transformed to snake case from their default CamelCase also.
		if (is_null($collection))
		{
			$collection = snake_case(str_plural(class_basename($related)));
		}

		// Now we're ready to create a new query builder for the related model and
		// the relationship instances for the relation. The relations will set
		// appropriate query constraint and entirely manages the hydrations.
		$query = $instance->newQuery();

		return new BelongsToMany($query, $this, $collection, $foreignKey, $otherKey, $caller['function']);
	}

    /**
     * Get a new query builder instance for the connection.
     *
     * @return Builder
     */
    protected function newBaseQueryBuilder()
    {
        return new QueryBuilder($this->getConnection());
    }

    /**
     * Set the array of model attributes. No checking is done.
     *
     * @param  array  $attributes
     * @param  bool   $sync
     * @return void
     */
    public function setRawAttributes(array $attributes, $sync = false)
    {
        foreach($attributes as $key => &$value)
        {
            // Convert MongoId to string
            if ($value instanceof MongoId)
            {
                $value = (string) $value;
            }

            // Convert MongoDate to string
            else if ($value instanceof MongoDate)
            {
                $value = $this->asDateTime($value)->format('Y-m-d H:i:s');
            }
        }

        parent::setRawAttributes($attributes, $sync);
    }

    /**
     * Remove one or more fields.
     *
     * @param  mixed $columns
     * @return int
     */
    public function dropColumn($columns)
    {
        if (!is_array($columns)) $columns = array($columns);

        // Unset attributes
        foreach ($columns as $column)
        {
            $this->__unset($column);
        }

        // Perform unset only on current document
        return $query = $this->newQuery()->where($this->getKeyName(), $this->getKey())->unset($columns);
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
        // Unset method
        if ($method == 'unset')
        {
            return call_user_func_array(array($this, 'dropColumn'), $parameters);
        }

        return parent::__call($method, $parameters);
    }

}