<?php namespace Jenssegers\Mongodb;

use Illuminate\Database\Eloquent\Collection;
use Jenssegers\Mongodb\DatabaseManager as Resolver;
use Jenssegers\Mongodb\Eloquent\Builder;
use Jenssegers\Mongodb\Query\Builder as QueryBuilder;
use Jenssegers\Mongodb\Relations\EmbedsMany;
use Jenssegers\Mongodb\Relations\EmbedsOne;

use Carbon\Carbon;
use DateTime;
use MongoId;
use MongoDate;

abstract class Model extends \Jenssegers\Eloquent\Model {

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
     * The attributes that should be exposed for toArray and toJson.
     *
     * @var array
     */
    protected $exposed = array();

    /**
     * The connection resolver instance.
     *
     * @var \Illuminate\Database\ConnectionResolverInterface
     */
    protected static $resolver;

    /**
     * Custom accessor for the model's id.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function getIdAttribute($value)
    {
        // If we don't have a value for 'id', we will use the Mongo '_id' value.
        // This allows us to work with models in a more sql-like way.
        if ( ! $value and array_key_exists('_id', $this->attributes))
        {
            $value = $this->attributes['_id'];
        }

        // Convert MongoId's to string.
        if ($value instanceof MongoId)
        {
            return (string) $value;
        }

        return $value;
    }

    /**
     * Define an embedded one-to-many relationship.
     *
     * @param  string  $related
     * @param  string  $collection
     * @return \Illuminate\Database\Eloquent\Relations\EmbedsMany
     */
    protected function embedsMany($related, $localKey = null, $foreignKey = null, $relation = null)
    {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relatinoships.
        if (is_null($relation))
        {
            list(, $caller) = debug_backtrace(false);

            $relation = $caller['function'];
        }

        if (is_null($localKey))
        {
            $localKey = '_' . $relation;
        }

        if (is_null($foreignKey))
        {
            $foreignKey = snake_case(class_basename($this));
        }

        $query = $this->newQuery();

        $instance = new $related;

        return new EmbedsMany($query, $this, $instance, $localKey, $foreignKey, $relation);
    }

    /**
     * Define an embedded one-to-many relationship.
     *
     * @param  string  $related
     * @param  string  $collection
     * @return \Illuminate\Database\Eloquent\Relations\EmbedsMany
     */
    protected function embedsOne($related, $localKey = null, $foreignKey = null, $relation = null)
    {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relatinoships.
        if (is_null($relation))
        {
            list(, $caller) = debug_backtrace(false);

            $relation = $caller['function'];
        }

        if (is_null($localKey))
        {
            $localKey = '_' . $relation;
        }

        if (is_null($foreignKey))
        {
            $foreignKey = snake_case(class_basename($this));
        }

        $query = $this->newQuery();

        $instance = new $related;

        return new EmbedsOne($query, $this, $instance, $localKey, $foreignKey, $relation);
    }

    /**
     * Convert a DateTime to a storable MongoDate object.
     *
     * @param  DateTime|int  $value
     * @return MongoDate
     */
    public function fromDateTime($value)
    {
        // If the value is already a MongoDate instance, we don't need to parse it.
        if ($value instanceof MongoDate)
        {
            return $value;
        }

        // Let Eloquent convert the value to a DateTime instance.
        if ( ! $value instanceof DateTime)
        {
            $value = parent::asDateTime($value);
        }

        return new MongoDate($value->getTimestamp());
    }

    /**
     * Return a timestamp as DateTime object.
     *
     * @param  mixed  $value
     * @return DateTime
     */
    protected function asDateTime($value)
    {
        // Convert MongoDate instances.
        if ($value instanceof MongoDate)
        {
            return Carbon::createFromTimestamp($value->sec);
        }

        return parent::asDateTime($value);
    }

    /**
     * Get the format for database stored dates.
     *
     * @return string
     */
    protected function getDateFormat()
    {
        return 'Y-m-d H:i:s';
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
     * Get an attribute from the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        // Check if the key is an array dot notation.
        if (strpos($key, '.') !== false)
        {
            $attributes = array_dot($this->attributes);

            if (array_key_exists($key, $attributes))
            {
                return $this->getAttributeValue($key);
            }
        }

        return parent::getAttribute($key);
    }

    /**
     * Get an attribute from the $attributes array.
     *
     * @param  string  $key
     * @return mixed
     */
    protected function getAttributeFromArray($key)
    {
        if (array_key_exists($key, $this->attributes))
        {
            return $this->attributes[$key];
        }

        else if (strpos($key, '.') !== false)
        {
            $attributes = array_dot($this->attributes);

            if (array_key_exists($key, $attributes))
            {
                return $attributes[$key];
            }
        }
    }

    /**
     * Set a given attribute on the model.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function setAttribute($key, $value)
    {
        // Convert _id to MongoId.
        if ($key == '_id' and is_string($value))
        {
            $builder = $this->newBaseQueryBuilder();
            $value = $builder->convertKey($value);
        }

        parent::setAttribute($key, $value);
    }

    /**
     * Convert the model's attributes to an array.
     *
     * @return array
     */
    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();

        // Because the original Eloquent never returns objects, we convert
        // MongoDB related objects to a string representation. This kind
        // of mimics the SQL behaviour so that dates are formatted
        // nicely when your models are converted to JSON.
        foreach ($attributes as $key => &$value)
        {
            if ($value instanceof MongoId)
            {
                $value = (string) $value;
            }

            // If the attribute starts with an underscore, it might be the
            // internal array of embedded documents. In that case, we need
            // to hide these from the output so that the relation-based
            // attribute can take over.
            else if (starts_with($key, '_') and ! in_array($key, $this->exposed))
            {
                $camelKey = camel_case($key);

                // If we can find a method that responds to this relation we
                // will remove it from the output.
                if (method_exists($this, $camelKey))
                {
                    unset($attributes[$key]);
                }
            }
        }

        return $attributes;
    }

    /**
     * Remove one or more fields.
     *
     * @param  mixed $columns
     * @return int
     */
    public function drop($columns)
    {
        if ( ! is_array($columns)) $columns = array($columns);

        // Unset attributes
        foreach ($columns as $column)
        {
            $this->__unset($column);
        }

        // Perform unset only on current document
        return $this->newQuery()->where($this->getKeyName(), $this->getKey())->unset($columns);
    }

    /**
     * Append one or more values to an array.
     *
     * @return mixed
     */
    public function push()
    {
        if ($parameters = func_get_args())
        {
            $query = $this->setKeysForSaveQuery($this->newQuery());

            return call_user_func_array(array($query, 'push'), $parameters);
        }

        return parent::push();
    }

    /**
     * Remove one or more values from an array.
     *
     * @return mixed
     */
    public function pull()
    {
        $query = $this->setKeysForSaveQuery($this->newQuery());

        return call_user_func_array(array($query, 'pull'), func_get_args());
    }

    /**
     * Set the exposed attributes for the model.
     *
     * @param  array  $exposed
     * @return void
     */
    public function setExposed(array $exposed)
    {
        $this->exposed = $exposed;
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param  \Jenssegers\Mongodb\Query\Builder $query
     * @return \Jenssegers\Mongodb\Eloquent\Builder|static
     */
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
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
            return call_user_func_array(array($this, 'drop'), $parameters);
        }

        return parent::__call($method, $parameters);
    }

}
