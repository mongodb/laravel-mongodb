<?php namespace Jenssegers\Mongodb;

use Illuminate\Database\Eloquent\Collection;
use Jenssegers\Mongodb\DatabaseManager as Resolver;
use Jenssegers\Mongodb\Eloquent\Builder;
use Jenssegers\Mongodb\Query\Builder as QueryBuilder;
use Jenssegers\Mongodb\Relations\EmbedsMany;

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
     * The connection resolver instance.
     *
     * @var \Illuminate\Database\ConnectionResolverInterface
     */
    protected static $resolver;

    /**
     * Custom accessor for the model's id.
     *
     * @return string
     */
    public function getIdAttribute($value)
    {
        // If there is an actual id attribute, then return that.
        if ($value) return $value;

        // Return primary key value if present
        if (array_key_exists($this->getKeyName(), $this->attributes)) return $this->attributes[$this->getKeyName()];
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
     * Get an attribute from the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        $attribute = parent::getAttribute($key);

        // If the attribute is a MongoId object, return it as a string.
        // This is makes Eloquent relations a lot easier.
        if ($attribute instanceof MongoId)
        {
            return (string) $attribute;
        }

        return $attribute;
    }

    /**
     * Convert the model's attributes to an array.
     *
     * @return array
     */
    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();

        foreach ($attributes as &$value)
        {
            /**
             * Here we convert MongoDate instances to string. This mimics
             * original SQL behaviour so that dates are formatted nicely
             * when your models are converted to JSON.
             */
            if ($value instanceof MongoDate)
            {
                $value = $this->asDateTime($value)->format($this->getDateFormat());
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
        if (!is_array($columns)) $columns = array($columns);

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
