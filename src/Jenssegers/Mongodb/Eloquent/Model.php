<?php namespace Jenssegers\Mongodb\Eloquent;

use DateTime, MongoId, MongoDate, Carbon\Carbon;
use Illuminate\Database\Eloquent\Model as BaseModel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Jenssegers\Mongodb\Query\Builder as QueryBuilder;
use Jenssegers\Mongodb\Eloquent\Builder;
use Jenssegers\Mongodb\Eloquent\HybridRelations;
use Jenssegers\Mongodb\Relations\EmbedsOneOrMany;
use Jenssegers\Mongodb\Relations\EmbedsMany;
use Jenssegers\Mongodb\Relations\EmbedsOne;

abstract class Model extends BaseModel {

    use HybridRelations;

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
     * Get the table qualified key name.
     *
     * @return string
     */
    public function getQualifiedKeyName()
    {
    	return $this->getKeyName();
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

        $localKey = $localKey ?: $relation;

        $foreignKey = $foreignKey ?: snake_case(class_basename($this));

        $instance = new $related;

        return new EmbedsMany($this->newQuery(), $this, $instance, $foreignKey, $localKey, $relation);
    }

    /**
     * Define an embedded one-to-one relationship.
     *
     * @param  string  $related
     * @param  string  $foreignKey
     * @param  string  $localKey
     * @return \Illuminate\Database\Eloquent\Relations\EmbedsOne
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

        $localKey = $localKey ?: $relation;

        $foreignKey = $foreignKey ?: snake_case(class_basename($this));

        $instance = new $related;

        return new EmbedsOne($this->newQuery(), $this, $instance, $foreignKey, $localKey, $relation);
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
        // Array dot notation support.
        if (str_contains($key, '.') and array_has($this->attributes, $key))
        {
            return $this->getAttributeValue($key);
        }

        // If the "attribute" exists as a method on the model, it may be an
        // embedded model. If so, we need to return the result before it
        // is handled by the parent method.
        if (method_exists($this, $key))
        {
            $relations = $this->$key();

            if ($relations instanceof EmbedsOne or $relations instanceof EmbedsMany)
            {
                // If the key already exists in the relationships array, it just means the
                // relationship has already been loaded, so we'll just return it out of
                // here because there is no need to query within the relations twice.
                if (array_key_exists($key, $this->relations))
                {
                    return $this->relations[$key];
                }

                // If the "attribute" exists as a method on the model, we will just assume
                // it is a relationship and will load and return results from the query
                // and hydrate the relationship's value on the "relationships" array.
                if (method_exists($this, $key))
                {
                    return $this->getRelationshipFromMethod($key);
                }
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
        // Array dot notation support.
        if (str_contains($key, '.') and $attribute = array_get($this->attributes, $key))
        {
            return $attribute;
        }

        return parent::getAttributeFromArray($key);
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

        // Support keys in dot notation.
        elseif (str_contains($key, '.'))
        {
            if (in_array($key, $this->getDates()) && $value)
            {
                $value = $this->fromDateTime($value);
            }

            array_set($this->attributes, $key, $value); return;
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
        if ( ! is_array($columns)) $columns = [$columns];

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
            $unique = false;

            if (count($parameters) == 3)
            {
                list($column, $values, $unique) = $parameters;
            }
            else
            {
                list($column, $values) = $parameters;
            }

            // Do batch push by default.
            if ( ! is_array($values)) $values = [$values];

            $query = $this->setKeysForSaveQuery($this->newQuery());

            $this->pushAttributeValues($column, $values, $unique);

            return $query->push($column, $values, $unique);
        }

        return parent::push();
    }

    /**
     * Remove one or more values from an array.
     *
     * @return mixed
     */
    public function pull($column, $values)
    {
        // Do batch pull by default.
        if ( ! is_array($values)) $values = [$values];

        $query = $this->setKeysForSaveQuery($this->newQuery());

        $this->pullAttributeValues($column, $values);

        return $query->pull($column, $values);
    }

    /**
     * Append one or more values to the underlying attribute value and sync with original.
     *
     * @param  string  $column
     * @param  array   $values
     * @param  bool    $unique
     * @return void
     */
    protected function pushAttributeValues($column, array $values, $unique = false)
    {
        $current = $this->getAttributeFromArray($column) ?: [];

        foreach ($values as $value)
        {
            // Don't add duplicate values when we only want unique values.
            if ($unique and in_array($value, $current)) continue;

            array_push($current, $value);
        }

        $this->attributes[$column] = $current;

        $this->syncOriginalAttribute($column);
    }

    /**
     * Rempove one or more values to the underlying attribute value and sync with original.
     *
     * @param  string  $column
     * @param  array   $values
     * @return void
     */
    protected function pullAttributeValues($column, array $values)
    {
        $current = $this->getAttributeFromArray($column) ?: [];

        foreach ($values as $value)
        {
            $keys = array_keys($current, $value);

            foreach ($keys as $key)
            {
                unset($current[$key]);
            }
        }

        $this->attributes[$column] = array_values($current);

        $this->syncOriginalAttribute($column);
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
     * Get a new query builder instance for the connection.
     *
     * @return Builder
     */
    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();

        return new QueryBuilder($connection, $connection->getPostProcessor());
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
            return call_user_func_array([$this, 'drop'], $parameters);
        }

        return parent::__call($method, $parameters);
    }

}
