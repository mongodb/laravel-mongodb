<?php

namespace Jenssegers\Mongodb\Eloquent;

use Carbon\Carbon;
use DateTime;
use Illuminate\Database\Eloquent\Model as BaseModel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Jenssegers\Mongodb\Query\Builder as QueryBuilder;
use MongoDB\BSON\ObjectID;
use MongoDB\BSON\UTCDateTime;
use Illuminate\Contracts\Queue\QueueableEntity;
use Illuminate\Contracts\Queue\QueueableCollection;

abstract class Model extends BaseModel
{
    use HybridRelations, EmbedsRelations;

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
     * Custom accessor for the model's id.
     *
     * @param  mixed $value
     * @return mixed
     */
    public function getIdAttribute($value = null)
    {
        // If we don't have a value for 'id', we will use the Mongo '_id' value.
        // This allows us to work with models in a more sql-like way.
        if (!$value && array_key_exists('_id', $this->attributes)) {
            $value = $this->attributes['_id'];
        }

        // Convert ObjectID to string.
        if ($value instanceof ObjectID) {
            return (string) $value;
        }

        return $value;
    }

    /**
     * @inheritdoc
     */
    public function getQualifiedKeyName()
    {
        return $this->getKeyName();
    }

    /**
     * @inheritdoc
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
     * @inheritdoc
     */
    protected function asDateTime($value)
    {
        // Convert UTCDateTime instances.
        if ($value instanceof UTCDateTime) {
            return Carbon::createFromTimestamp($value->toDateTime()->getTimestamp());
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        return parent::asDateTime($value);
    }

    /**
     * @inheritdoc
     */
    public function getDateFormat()
    {
        return $this->dateFormat ?: 'Y-m-d H:i:s';
    }

    /**
     * @inheritdoc
     */
    public function freshTimestamp()
    {
        return new UTCDateTime(time() * 1000);
    }

    /**
     * @inheritdoc
     */
    public function getTable()
    {
        return $this->collection ?: parent::getTable();
    }

    /**
     * @inheritdoc
     */
    public function getAttribute($key)
    {
        if (!$key) {
            return;
        }

        // Dot notation support.
        if (Str::contains($key, '.') && Arr::has($this->attributes, $key)) {
            return $this->getAttributeValue($key);
        }

        // This checks for embedded relation support.
        if (method_exists($this, $key) && !method_exists(self::class, $key)) {
            return $this->getRelationValue($key);
        }

        return parent::getAttribute($key);
    }

    /**
     * @inheritdoc
     */
    protected function getAttributeFromArray($key)
    {
        // Support keys in dot notation.
        if (Str::contains($key, '.')) {
            return Arr::get($this->attributes, $key);
        }

        return parent::getAttributeFromArray($key);
    }

    /**
     * Is the given array an associative array
     *
     * @param array $arr
     *
     * @return bool
     */
    private function isAssoc(array $arr)
    {
        // if it's empty, we presume that it's sequential
        if ([] === $arr) {
            return false;
        }

        // if $arr[0] does not exist, it must to be a associative array
        if (!array_key_exists(0, $arr)) {
            return true;
        }

        // strict compare the array keys against a range of the same length
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Flatten an associative array of attributes to dot notation
     *
     * @param string $key
     * @param        $value
     *
     * @return \Illuminate\Support\Collection
     */
    private function flattenAttributes($key, $value)
    {
        $values = collect();

        if (is_array($value) && $this->isAssoc($value)) {
            foreach ($value as $k => $value) {
                $values = $values->merge($this->flattenAttributes("$key.$k", $value));
            }
        } else {
            $values->put($key, $value);
        }

        return $values;
    }

    /**
     * @inheritdoc
     */
    public function setAttribute($key, $value)
    {
        $values = $this->flattenAttributes($key, $value);

        $values->each(function ($val, $k) {
            // Convert to ObjectID.
            if ($this->isCastableToObjectId($k)) {
                $builder = $this->newBaseQueryBuilder();

                if (is_array($val)) {
                    foreach ($val as &$v) {
                        $v = $builder->convertKey($v);
                    }
                } else {
                    $val = $builder->convertKey($val);
                }
            } // Convert to UTCDateTime
            else {
                if (in_array($k, $this->getDates()) && $val) {
                    if (is_array($val)) {
                        foreach ($val as &$v) {
                            $v = $this->fromDateTime($v);
                        }
                    } else {
                        $val = $this->fromDateTime($val);
                    }
                }
            }

            // Support keys in dot notation.
            if (Str::contains($k, '.')) {
                Arr::set($this->attributes, $k, $val);

                return;
            }

            parent::setAttribute($k, $val);
        });

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function attributesToArray()
    {
        // Convert dates before parent method call so that all dates have the same format
        foreach ($this->getDates() as $key) {
            if (!Arr::has($this->attributes, $key)) {
                continue;
            }

            $val = Arr::get($this->attributes, $key);

            if (is_array($val)) {
                foreach ($val as &$v) {
                    $v = $this->asDateTime($v)->format('Y-m-d H:i:s.v');
                }
            } elseif ($val) {
                $val = $this->asDateTime($val)->format('Y-m-d H:i:s.v');
            }

            Arr::set($this->attributes, $key, $val);
        }

        $attributes = parent::attributesToArray();

        // Because the original Eloquent never returns objects, we convert
        // MongoDB related objects to a string representation. This kind
        // of mimics the SQL behaviour so that dates are formatted
        // nicely when your models are converted to JSON.
        $this->getObjectIds()->each(function ($key) use (&$attributes) {
            if (!Arr::has($attributes, $key)) {
                return;
            }

            $value = Arr::get($attributes, $key);

            if (is_array($value)) {
                foreach ($value as &$val) {
                    $val = (string) $val;
                }
            } else {
                $value = (string) $value;
            }

            Arr::set($attributes, $key, $value);
        });

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    public function getCasts()
    {
        return $this->casts;
    }

    /**
     * Get a list of the castable ObjectId fields
     *
     * @return \Illuminate\Support\Collection
     */
    public function getObjectIds()
    {
        return collect($this->getCasts())->filter(function ($val) {
            return 'objectid' === $val;
        })->keys()->push('_id');
    }

    /**
     * @inheritdoc
     */
    protected function originalIsEquivalent($key, $current)
    {
        if (!array_key_exists($key, $this->original)) {
            return false;
        }

        $original = $this->getOriginal($key);

        if ($current === $original) {
            return true;
        }

        if (null === $current) {
            return false;
        }

        if ($this->isDateAttribute($key)) {
            $current = $current instanceof UTCDateTime ? $this->asDateTime($current) : $current;
            $original = $original instanceof UTCDateTime ? $this->asDateTime($original) : $original;

            return $current == $original;
        }

        if ($this->hasCast($key)) {
            return $this->castAttribute($key, $current) ===
                $this->castAttribute($key, $original);
        }

        return is_numeric($current) && is_numeric($original)
            && strcmp((string) $current, (string) $original) === 0;
    }

    /**
     * Remove one or more fields.
     *
     * @param  mixed $columns
     * @return int
     */
    public function drop($columns)
    {
        $columns = Arr::wrap($columns);

        // Unset attributes
        foreach ($columns as $column) {
            $this->__unset($column);
        }

        // Perform unset only on current document
        return $this->newQuery()->where($this->getKeyName(), $this->getKey())->unset($columns);
    }

    /**
     * @inheritdoc
     */
    public function push()
    {
        if ($parameters = func_get_args()) {
            $unique = false;

            if (count($parameters) === 3) {
                list($column, $values, $unique) = $parameters;
            } else {
                list($column, $values) = $parameters;
            }

            // Do batch push by default.
            $values = Arr::wrap($values);

            $query = $this->setKeysForSaveQuery($this->newQuery());

            $this->pushAttributeValues($column, $values, $unique);

            // Convert $casts on push()
            if ($this->isCastableToObjectId($column)) {
                foreach ($values as &$value) {
                    if (is_string($value)) {
                        $value = new ObjectId($value);
                    }
                }
            }

            // Convert dates on push()
            if (in_array($column, $this->getDates())) {
                foreach ($values as &$value) {
                    $value = $this->fromDateTime($value);
                }
            }

            return $query->push($column, $values, $unique);
        }

        return parent::push();
    }

    /**
     * Remove one or more values from an array.
     *
     * @param  string $column
     * @param  mixed $values
     * @return mixed
     */
    public function pull($column, $values)
    {
        // Do batch pull by default.
        $values = Arr::wrap($values);

        $query = $this->setKeysForSaveQuery($this->newQuery());

        $this->pullAttributeValues($column, $values);

        return $query->pull($column, $values);
    }

    /**
     * Append one or more values to the underlying attribute value and sync with original.
     *
     * @param  string $column
     * @param  array $values
     * @param  bool $unique
     */
    protected function pushAttributeValues($column, array $values, $unique = false)
    {
        $current = $this->getAttributeFromArray($column) ?: [];

        foreach ($values as $value) {
            // Don't add duplicate values when we only want unique values.
            if ($unique && (!is_array($current) || in_array($value, $current))) {
                continue;
            }

            $current[] = $value;
        }

        // Support for dot notation keys
        if (Str::contains($column, '.')) {
            Arr::set($this->attributes, $column, $current);
        } else {
            $this->attributes[$column] = $current;
        }

        $this->syncOriginalAttribute($column);
    }

    /**
     * @inheritdoc
     */
    public function syncOriginalAttribute($attribute)
    {
        // Support for dot notation keys
        if (Str::contains($attribute, '.')) {
            $value = Arr::get($this->attributes, $attribute);

            Arr::set($this->original, $attribute, $value);
        } else {
            $this->original[$attribute] = $this->attributes[$attribute];
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function addDateAttributesToArray(array $attributes)
    {
        foreach ($this->getDates() as $key) {
            if (!isset($attributes[$key])) {
                continue;
            }

            // Support for array of dates
            if (is_array($attributes[$key])) {
                foreach ($attributes[$key] as &$value) {
                    $value = $this->serializeDate($this->asDateTime($value));
                }
            } else {
                $attributes[$key] = $this->serializeDate($this->asDateTime($attributes[$key]));
            }
        }

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    public function getAttributeValue($key)
    {
        $value = $this->getAttributeFromArray($key);

        // If the attribute is listed as a date, we will convert it to a DateTime
        // instance on retrieval, which makes it quite convenient to work with
        // date fields without having to create a mutator for each property.
        if (in_array($key, $this->getDates()) && !is_null($value)) {
            if (is_array($value)) {
                foreach ($value as &$val) {
                    $val = $this->asDateTime($val);
                }
            } else {
                $value = $this->asDateTime($value);
            }

            return $value;
        }

        //  Also convert objectIds to strings
        if ($this->getObjectIds()->search($key) !== false && !is_null($value)) {
            if (is_array($value)) {
                foreach ($value as &$val) {
                    $val = (string) $val;
                }
            } else {
                $value = (string) $value;
            }

            return $value;
        }

        return parent::getAttributeFromArray($key);
    }

    /**
     * Remove one or more values to the underlying attribute value and sync with original.
     *
     * @param  string $column
     * @param  array $values
     */
    protected function pullAttributeValues($column, array $values)
    {
        $current = $this->getAttributeFromArray($column) ?: [];

        if (is_array($current)) {
            foreach ($values as $value) {
                $keys = array_keys($current, $value);

                foreach ($keys as $key) {
                    unset($current[$key]);
                }
            }
        }

        $this->attributes[$column] = array_values($current);

        $this->syncOriginalAttribute($column);
    }

    /**
     * @inheritdoc
     */
    public function getForeignKey()
    {
        return Str::snake(class_basename($this)) . '_' . ltrim($this->primaryKey, '_');
    }

    /**
     * Set the parent relation.
     *
     * @param  \Illuminate\Database\Eloquent\Relations\Relation $relation
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
     * @inheritdoc
     */
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
    }

    /**
     * @inheritdoc
     */
    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();

        return (new QueryBuilder($connection, $connection->getPostProcessor()))->casts($this->casts);
    }

    /**
     * @inheritdoc
     */
    protected function removeTableFromKey($key)
    {
        return $key;
    }

    /**
     * Get the queueable relationships for the entity.
     *
     * @return array
     */
    public function getQueueableRelations()
    {
        $relations = [];

        foreach ($this->getRelationsWithoutParent() as $key => $relation) {
            if (method_exists($this, $key)) {
                $relations[] = $key;
            }

            if ($relation instanceof QueueableCollection) {
                foreach ($relation->getQueueableRelations() as $collectionValue) {
                    $relations[] = $key.'.'.$collectionValue;
                }
            }

            if ($relation instanceof QueueableEntity) {
                foreach ($relation->getQueueableRelations() as $entityKey => $entityValue) {
                    $relations[] = $key.'.'.$entityValue;
                }
            }
        }

        return array_unique($relations);
    }

    /**
     * Get loaded relations for the instance without parent.
     *
     * @return array
     */
    protected function getRelationsWithoutParent()
    {
        $relations = $this->getRelations();

        if ($parentRelation = $this->getParentRelation()) {
            unset($relations[$parentRelation->getQualifiedForeignKeyName()]);
        }

        return $relations;
    }

    /**
     * @inheritdoc
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
     * @inheritdoc
     */
    protected function castAttribute($key, $value)
    {
        if (is_null($value)) {
            return $value;
        }

        if ($this->getCastType($key) === 'objectid') {
            if (is_array($value)) {
                foreach ($value as &$val) {
                    $val = (string) $val;
                }

                return $value;
            }

            return (string) $value;
        }

        return parent::castAttribute($key, $value);
    }

    /**
     * Is the field an ObjectId
     *
     * @param string $key
     * @return bool
     */
    protected function isCastableToObjectId($key)
    {
        return '_id' === $key || $this->hasCast($key, ['objectid']);
    }
}
