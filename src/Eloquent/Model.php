<?php

namespace Moloquent\Eloquent;

use Carbon\Carbon;
use DateTime;
use Illuminate\Database\Eloquent\Model as BaseModel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Moloquent\Query\Builder as QueryBuilder;
use Moloquent\Relations\EmbedsMany;
use Moloquent\Relations\EmbedsOne;
use Moloquent\Relations\EmbedsOneOrMany;
use MongoDB\BSON\ObjectID;
use MongoDB\BSON\UTCDateTime;
use ReflectionMethod;

abstract class Model extends BaseModel
{
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
     * The parent relation instance.
     *
     * @var Relation
     */
    protected $parentRelation;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $saveCasts = [];

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
        if (!$value and array_key_exists('_id', $this->attributes)) {
            $value = $this->attributes['_id'];
        }

        // Convert ObjectID to string.
        if ($value instanceof ObjectID) {
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
     * @param string $related
     * @param string $localKey
     * @param string $foreignKey
     * @param string $relation
     *
     * @return \Moloquent\Relations\EmbedsMany
     */
    protected function embedsMany($related, $localKey = null, $foreignKey = null, $relation = null)
    {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relatinoships.
        if (is_null($relation)) {
            list(, $caller) = debug_backtrace(false);

            $relation = $caller['function'];
        }

        if (is_null($localKey)) {
            $localKey = $relation;
        }

        if (is_null($foreignKey)) {
            $foreignKey = snake_case(class_basename($this));
        }

        $query = $this->newQuery();

        $instance = new $related();

        return new EmbedsMany($query, $this, $instance, $localKey, $foreignKey, $relation);
    }

    /**
     * Define an embedded one-to-many relationship.
     *
     * @param string $related
     * @param string $localKey
     * @param string $foreignKey
     * @param string $relation
     *
     * @return \Moloquent\Relations\EmbedsOne
     */
    protected function embedsOne($related, $localKey = null, $foreignKey = null, $relation = null)
    {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relatinoships.
        if (is_null($relation)) {
            list(, $caller) = debug_backtrace(false);

            $relation = $caller['function'];
        }

        if (is_null($localKey)) {
            $localKey = $relation;
        }

        if (is_null($foreignKey)) {
            $foreignKey = snake_case(class_basename($this));
        }

        $query = $this->newQuery();

        $instance = new $related();

        return new EmbedsOne($query, $this, $instance, $localKey, $foreignKey, $relation);
    }

    /**
     * Convert a DateTime to a storable UTCDateTime object.
     *
     * @param DateTime|int $value
     *
     * @return UTCDateTime
     */
    public function fromDateTime($value)
    {
        // If the value is already a UTCDateTime instance, we don't need to parse it.
        if ($value instanceof UTCDateTime) {
            return $value;
        }

        // Let Eloquent convert the value to a DateTime instance.
        if (!$value instanceof DateTime) {
            $value = parent::asDateTime($value);
        }

        return new UTCDateTime($value->getTimestamp() * 1000);
    }

    /**
     * Return a timestamp as DateTime object.
     *
     * @param mixed $value
     *
     * @return DateTime
     */
    protected function asDateTime($value)
    {
        // Convert UTCDateTime instances.
        if ($value instanceof UTCDateTime) {
            return Carbon::createFromTimestamp($value->toDateTime()->getTimestamp());
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
        return $this->dateFormat ?: 'Y-m-d H:i:s';
    }

    /**
     * Get a fresh timestamp for the model.
     *
     * @return UTCDateTime
     */
    public function freshTimestamp()
    {
        return new UTCDateTime(round(microtime(true) * 1000));
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return $this->collection ?: parent::getTable();
    }

    /**
     * Get an attribute from the model.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getAttribute($key)
    {
        // Check if the key is an array dot notation.
        if (str_contains($key, '.') and array_has($this->attributes, $key)) {
            return $this->getAttributeValue($key);
        }

        // Eloquent behaviour would prioritise the mutator, so Check for hasGetMutator first
        if ($this->hasGetMutator($key)) {
            return $this->getAttributeValue($key);
        }

        $camelKey = camel_case($key);

        // If the "attribute" exists as a method on the model, it may be an
        // embedded model. If so, we need to return the result before it
        // is handled by the parent method.
        if (method_exists($this, $camelKey)) {
            $method = new ReflectionMethod(get_called_class(), $camelKey);

            // Ensure the method is not static to avoid conflicting with Eloquent methods.
            if (!$method->isStatic()) {
                $relations = $this->$camelKey();

                // This attribute matches an embedsOne or embedsMany relation so we need
                // to return the relation results instead of the interal attributes.
                if ($relations instanceof EmbedsOneOrMany) {
                    // If the key already exists in the relationships array, it just means the
                    // relationship has already been loaded, so we'll just return it out of
                    // here because there is no need to query within the relations twice.
                    if (array_key_exists($key, $this->relations)) {
                        return $this->relations[$key];
                    }

                    // Get the relation results.
                    return $this->getRelationshipFromMethod($key, $camelKey);
                }

                if ($relations instanceof Relation) {
                    // If the key already exists in the relationships array, it just means the
                    // relationship has already been loaded, so we'll just return it out of
                    // here because there is no need to query within the relations twice.
                    if (array_key_exists($key, $this->relations) && $this->relations[$key] != null) {
                        return $this->relations[$key];
                    }

                    // Get the relation results.
                    return $this->getRelationshipFromMethod($key, $camelKey);
                }
            }
        }

        return parent::getAttribute($key);
    }

    /**
     * Get an attribute from the $attributes array.
     *
     * @param string $key
     *
     * @return mixed
     */
    protected function getAttributeFromArray($key)
    {
        // Support keys in dot notation.
        if (str_contains($key, '.')) {
            $attributes = array_dot($this->attributes);

            if (array_key_exists($key, $attributes)) {
                return $attributes[$key];
            }
        }

        return parent::getAttributeFromArray($key);
    }

    /**
     * Set a given attribute on the model.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function setAttribute($key, $value)
    {
        // cast data for saving.
        // set _id to converted into ObjectID if its possible.
        $this->setRelationCast($key);
        $value = $this->castAttribute($key, $value, 'set');

        // Support keys in dot notation.
        if (str_contains($key, '.')) {
            if (in_array($key, $this->getDates()) && $value) {
                $value = $this->fromDateTime($value);
            }

            array_set($this->attributes, $key, $value);

            return;
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
        foreach ($attributes as $key => &$value) {
            if ($value instanceof ObjectID) {
                $value = (string) $value;
            }
        }

        // Convert dot-notation dates.
        foreach ($this->getDates() as $key) {
            if (str_contains($key, '.') and array_has($attributes, $key)) {
                array_set($attributes, $key, (string) $this->asDateTime(array_get($attributes, $key)));
            }
        }

        return $attributes;
    }

    /**
     * Determine if the new and old values for a given key are numerically equivalent.
     *
     * @param string $key
     *
     * @return bool
     */
    protected function originalIsNumericallyEquivalent($key)
    {
        $current = $this->attributes[$key];
        $original = $this->original[$key];

        // Date comparison.
        if (in_array($key, $this->getDates())) {
            $current = $current instanceof UTCDateTime ? $this->asDateTime($current) : $current;
            $original = $original instanceof UTCDateTime ? $this->asDateTime($original) : $original;

            return $current == $original;
        }

        return parent::originalIsNumericallyEquivalent($key);
    }

    /**
     * Remove one or more fields.
     *
     * @param mixed $columns
     *
     * @return int
     */
    public function drop($columns)
    {
        if (!is_array($columns)) {
            $columns = [$columns];
        }

        // Unset attributes
        foreach ($columns as $column) {
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
        if ($parameters = func_get_args()) {
            $unique = false;

            if (count($parameters) == 3) {
                list($column, $values, $unique) = $parameters;
            } else {
                list($column, $values) = $parameters;
            }

            // Do batch push by default.
            if (!is_array($values)) {
                $values = [$values];
            }

            $query = $this->setKeysForSaveQuery($this->newQuery());

            $this->pushAttributeValues($column, $values, $unique);

            return $query->push($column, $values, $unique);
        }

        return parent::push();
    }

    /**
     * Remove one or more values from an array.
     *
     * @param string $column
     * @param mixed  $values
     *
     * @return mixed
     */
    public function pull($column, $values)
    {
        // Do batch pull by default.
        if (!is_array($values)) {
            $values = [$values];
        }

        $query = $this->setKeysForSaveQuery($this->newQuery());

        $this->pullAttributeValues($column, $values);

        return $query->pull($column, $values);
    }

    /**
     * Append one or more values to the underlying attribute value and sync with original.
     *
     * @param string $column
     * @param array  $values
     * @param bool   $unique
     */
    protected function pushAttributeValues($column, array $values, $unique = false)
    {
        $current = $this->getAttributeFromArray($column) ?: [];

        foreach ($values as $value) {
            // Don't add duplicate values when we only want unique values.
            if ($unique and in_array($value, $current)) {
                continue;
            }

            array_push($current, $value);
        }

        $this->attributes[$column] = $current;

        $this->syncOriginalAttribute($column);
    }

    /**
     * Remove one or more values to the underlying attribute value and sync with original.
     *
     * @param string $column
     * @param array  $values
     */
    protected function pullAttributeValues($column, array $values)
    {
        $current = $this->getAttributeFromArray($column) ?: [];

        foreach ($values as $value) {
            $keys = array_keys($current, $value);

            foreach ($keys as $key) {
                unset($current[$key]);
            }
        }

        $this->attributes[$column] = array_values($current);

        $this->syncOriginalAttribute($column);
    }

    /**
     * Set the parent relation.
     *
     * @param \Illuminate\Database\Eloquent\Relations\Relation $relation
     */
    public function setParentRelation(Relation $relation)
    {
        $this->parentRelation = $relation;
    }

    /**
     * Get the parent relation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\Relation
     */
    public function getParentRelation()
    {
        return $this->parentRelation;
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param \Moloquent\Query\Builder $query
     *
     * @return \Moloquent\Eloquent\Builder|static
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
     * We just return original key here in order to support keys in dot-notation.
     *
     * @param string $key
     *
     * @return string
     */
    protected function removeTableFromKey($key)
    {
        return $key;
    }

    /**
     * Handle dynamic method calls into the method.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        // Unset method
        if ($method == 'unset') {
            return call_user_func_array([$this, 'drop'], $parameters);
        }

        return parent::__call($method, $parameters);
    }

    /**
     * setter for casts.
     *
     * @param $cast
     * @param string $castType
     *
     * @return void
     */
    public function setCasts($cast, $castType = 'get')
    {
        if ($castType == 'set') {
            $this->saveCasts = $cast;

            return;
        }
        $this->casts = $cast;
    }

    /**
     * Get the casts array.
     *
     * @param string $castType
     *
     * @return array
     */
    public function getCasts($castType = 'get')
    {
        if ($castType == 'set') {
            return $this->saveCasts;
        }

        return $this->casts;
    }

    /**
     * Get the type of save cast for a model attribute.
     *
     * @param string $key
     * @param string $castType
     *
     * @return string
     */
    protected function getCastType($key, $castType = 'get')
    {
        return trim(strtolower($this->getCasts($castType)[$key]));
    }

    /**
     * Determine whether an attribute should be cast to a native type.
     *
     * @param string            $key
     * @param array|string|null $types
     * @param string            $castType
     *
     * @return bool
     */
    public function hasCast($key, $types = null, $castType = 'get')
    {
        if (array_key_exists($key, $this->getCasts($castType))) {
            return $types ? in_array($this->getCastType($key, $castType), (array) $types, true) : true;
        }

        return false;
    }

    /**
     * check if driver uses mongoId in relations.
     *
     * @return bool
     */
    public function useMongoId()
    {
        return (bool) config('database.connections.mongodb.use_mongo_id', false);
    }

    /**
     * Cast an attribute to a mongo type.
     *
     * @param string $key
     * @param mixed  $value
     * @param string $castType
     *
     * @return mixed
     */
    public function castAttribute($key, $value, $castType = 'get')
    {
        if (is_null($value)) {
            return;
        }

        if (!$this->hasCast($key, null, $castType)) {
            return $value;
        }

        switch ($this->getCastType($key, $castType)) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
            case 'double':
                return (float) $value;
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'date':
            case 'utcdatetime':
            case 'mongodate':
                return $this->asMongoDate($value);
            case 'mongoid':
            case 'objectid':
                return $this->asMongoID($value);
            case 'timestamp':
                return $this->asTimeStamp($value);
            default:
                return $value;
        }
    }

    /**
     * convert value into ObjectID if its possible.
     *
     * @param $value
     *
     * @return UTCDatetime
     */
    protected function asMongoID($value)
    {
        if (is_string($value) and strlen($value) === 24 and ctype_xdigit($value)) {
            return new ObjectID($value);
        }

        return $value;
    }

    /**
     * convert value into UTCDatetime.
     *
     * @param $value
     *
     * @return UTCDatetime
     */
    protected function asMongoDate($value)
    {
        if ($value instanceof UTCDatetime) {
            return $value;
        }

        return new UTCDatetime($this->asTimeStamp($value) * 1000);
    }

    /**
     * add relation that ended with _id into objectId
     * if config allow it.
     *
     * @param $key
     */
    public function setRelationCast($key)
    {
        if ($key == '_id') {
            $this->saveCasts['_id'] = 'ObjectID';

            return;
        }
        if ($this->useMongoId()) {
            if (ends_with($key, '_id')) {
                $this->saveCasts[$key] = 'ObjectID';
            }
        }
    }
}
