<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Eloquent;

use BackedEnum;
use Carbon\CarbonInterface;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Contracts\Queue\QueueableCollection;
use Illuminate\Contracts\Queue\QueueableEntity;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Exceptions\MathException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use MongoDB\BSON\Binary;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\ObjectID;
use MongoDB\BSON\Type;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Laravel\Query\Builder as QueryBuilder;
use Stringable;
use ValueError;

use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_unique;
use function array_values;
use function class_basename;
use function count;
use function date_default_timezone_get;
use function explode;
use function func_get_args;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function ltrim;
use function method_exists;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strcmp;
use function trigger_error;
use function var_export;

use const E_USER_DEPRECATED;

trait DocumentModel
{
    use HybridRelations;
    use EmbedsRelations;

    /**
     * The parent relation instance.
     */
    private Relation $parentRelation;

    /**
     * List of field names to unset from the document on save.
     *
     * @var array{string, true}
     */
    private array $unset = [];

    /**
     * Custom accessor for the model's id.
     *
     * @param  mixed $value
     *
     * @return mixed
     */
    public function getIdAttribute($value = null)
    {
        // If we don't have a value for 'id', we will use the MongoDB '_id' value.
        // This allows us to work with models in a more sql-like way.
        if (! $value && array_key_exists('_id', $this->attributes)) {
            $value = $this->attributes['_id'];
        }

        // Convert ObjectID to string.
        if ($value instanceof ObjectID) {
            return (string) $value;
        }

        if ($value instanceof Binary) {
            return (string) $value->getData();
        }

        return $value;
    }

    /** @inheritdoc */
    public function getQualifiedKeyName()
    {
        return $this->getKeyName();
    }

    /** @inheritdoc */
    public function fromDateTime($value)
    {
        // If the value is already a UTCDateTime instance, we don't need to parse it.
        if ($value instanceof UTCDateTime) {
            return $value;
        }

        // Let Eloquent convert the value to a DateTime instance.
        if (! $value instanceof DateTimeInterface) {
            $value = parent::asDateTime($value);
        }

        return new UTCDateTime($value);
    }

    /** @inheritdoc */
    protected function asDateTime($value)
    {
        // Convert UTCDateTime instances to Carbon.
        if ($value instanceof UTCDateTime) {
            return Date::instance($value->toDateTime())
                ->setTimezone(new DateTimeZone(date_default_timezone_get()));
        }

        return parent::asDateTime($value);
    }

    /** @inheritdoc */
    public function getDateFormat()
    {
        return $this->dateFormat ?: 'Y-m-d H:i:s';
    }

    /** @inheritdoc */
    public function freshTimestamp()
    {
        return new UTCDateTime(Date::now());
    }

    /** @inheritdoc */
    public function getTable()
    {
        if (isset($this->collection)) {
            trigger_error('Since mongodb/laravel-mongodb 4.8: Using "$collection" property is deprecated. Use "$table" instead.', E_USER_DEPRECATED);

            return $this->collection;
        }

        return parent::getTable();
    }

    /** @inheritdoc */
    public function getAttribute($key)
    {
        if (! $key) {
            return null;
        }

        $key = (string) $key;

        // An unset attribute is null or throw an exception.
        if (isset($this->unset[$key])) {
            return $this->throwMissingAttributeExceptionIfApplicable($key);
        }

        // Dot notation support.
        if (str_contains($key, '.') && Arr::has($this->attributes, $key)) {
            return $this->getAttributeValue($key);
        }

        // This checks for embedded relation support.
        // Ignore methods defined in the class Eloquent Model or in this trait.
        if (
            method_exists($this, $key)
            && ! method_exists(Model::class, $key)
            && ! method_exists(DocumentModel::class, $key)
            && ! $this->hasAttributeGetMutator($key)
        ) {
            return $this->getRelationValue($key);
        }

        return parent::getAttribute($key);
    }

    /** @inheritdoc */
    protected function transformModelValue($key, $value)
    {
        $value = parent::transformModelValue($key, $value);
        // Casting attributes to any of date types, will convert that attribute
        // to a Carbon or CarbonImmutable instance.
        // @see Model::setAttribute()
        if ($this->hasCast($key) && $value instanceof CarbonInterface) {
            $value->settings(array_merge($value->getSettings(), ['toStringFormat' => $this->getDateFormat()]));

            // "date" cast resets the time to 00:00:00.
            $castType = $this->getCasts()[$key];
            if (str_starts_with($castType, 'date:') || str_starts_with($castType, 'immutable_date:')) {
                $value = $value->startOfDay();
            }
        }

        return $value;
    }

    /** @inheritdoc */
    protected function getCastType($key)
    {
        $castType = $this->getCasts()[$key];
        if ($this->isCustomDateTimeCast($castType) || $this->isImmutableCustomDateTimeCast($castType)) {
            $this->setDateFormat(Str::after($castType, ':'));
        }

        return parent::getCastType($key);
    }

    /** @inheritdoc */
    protected function getAttributeFromArray($key)
    {
        $key = (string) $key;

        // Support keys in dot notation.
        if (str_contains($key, '.')) {
            return Arr::get($this->attributes, $key);
        }

        return parent::getAttributeFromArray($key);
    }

    /** @inheritdoc */
    public function setAttribute($key, $value)
    {
        $key = (string) $key;

        $casts = $this->getCasts();
        if (array_key_exists($key, $casts)) {
            $castType = $this->getCastType($key);
            $castOptions = Str::after($casts[$key], ':');

            // Can add more native mongo type casts here.
            $value = match ($castType) {
                'decimal' => $this->fromDecimal($value, $castOptions),
                default   => $value,
            };
        }

        // Convert _id to ObjectID.
        if ($key === '_id' && is_string($value)) {
            $builder = $this->newBaseQueryBuilder();

            $value = $builder->convertKey($value);
        }

        // Support keys in dot notation.
        if (str_contains($key, '.')) {
            // Store to a temporary key, then move data to the actual key
            parent::setAttribute('__LARAVEL_TEMPORARY_KEY__', $value);

            Arr::set($this->attributes, $key, $this->attributes['__LARAVEL_TEMPORARY_KEY__'] ?? null);
            unset($this->attributes['__LARAVEL_TEMPORARY_KEY__']);

            return $this;
        }

        // Setting an attribute cancels the unset operation.
        unset($this->unset[$key]);

        return parent::setAttribute($key, $value);
    }

    /**
     * @param mixed $value
     *
     * @inheritdoc
     */
    protected function asDecimal($value, $decimals)
    {
        // Convert BSON to string.
        if ($this->isBSON($value)) {
            if ($value instanceof Binary) {
                $value = $value->getData();
            } elseif ($value instanceof Stringable) {
                $value = (string) $value;
            } else {
                throw new MathException('BSON type ' . $value::class . ' cannot be converted to string');
            }
        }

        return parent::asDecimal($value, $decimals);
    }

    /**
     * Change to mongo native for decimal cast.
     *
     * @param mixed $value
     * @param int   $decimals
     *
     * @return Decimal128
     */
    protected function fromDecimal($value, $decimals)
    {
        return new Decimal128($this->asDecimal($value, $decimals));
    }

    /** @inheritdoc */
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
            } elseif ($value instanceof Binary) {
                $value = (string) $value->getData();
            }
        }

        return $attributes;
    }

    /** @inheritdoc */
    public function getCasts()
    {
        return $this->casts;
    }

    /** @inheritdoc */
    public function getDirty()
    {
        $dirty = parent::getDirty();

        // The specified value in the $unset expression does not impact the operation.
        if ($this->unset !== []) {
            $dirty['$unset'] = $this->unset;
        }

        return $dirty;
    }

    /** @inheritdoc */
    public function originalIsEquivalent($key)
    {
        if (! array_key_exists($key, $this->original)) {
            return false;
        }

        // Calling unset on an attribute marks it as "not equivalent".
        if (isset($this->unset[$key])) {
            return false;
        }

        $attribute = Arr::get($this->attributes, $key);
        $original  = Arr::get($this->original, $key);

        if ($attribute === $original) {
            return true;
        }

        if ($attribute === null) {
            return false;
        }

        if ($this->isDateAttribute($key)) {
            $attribute = $attribute instanceof UTCDateTime ? $this->asDateTime($attribute) : $attribute;
            $original  = $original instanceof UTCDateTime ? $this->asDateTime($original) : $original;

            // Comparison on DateTimeInterface values
            // phpcs:disable SlevomatCodingStandard.Operators.DisallowEqualOperators.DisallowedEqualOperator
            return $attribute == $original;
        }

        if ($this->hasCast($key, static::$primitiveCastTypes)) {
            return $this->castAttribute($key, $attribute) ===
                $this->castAttribute($key, $original);
        }

        return is_numeric($attribute) && is_numeric($original)
            && strcmp((string) $attribute, (string) $original) === 0;
    }

    /** @inheritdoc */
    public function offsetUnset($offset): void
    {
        $offset = (string) $offset;

        if (str_contains($offset, '.')) {
            // Update the field in the subdocument
            Arr::forget($this->attributes, $offset);
        } else {
            parent::offsetUnset($offset);

            // Force unsetting even if the attribute is not set.
            // End user can optimize DB calls by checking if the attribute is set before unsetting it.
            $this->unset[$offset] = true;
        }
    }

    /** @inheritdoc */
    public function offsetSet($offset, $value): void
    {
        parent::offsetSet($offset, $value);

        // Setting an attribute cancels the unset operation.
        unset($this->unset[$offset]);
    }

    /**
     * Remove one or more fields.
     *
     * @deprecated Use unset() instead.
     *
     * @param  string|string[] $columns
     *
     * @return void
     */
    public function drop($columns)
    {
        $this->unset($columns);
    }

    /**
     * Remove one or more fields.
     *
     * @param  string|string[] $columns
     *
     * @return void
     */
    public function unset($columns)
    {
        $columns = Arr::wrap($columns);

        // Unset attributes
        foreach ($columns as $column) {
            $this->__unset($column);
        }
    }

    /** @inheritdoc */
    public function push()
    {
        $parameters = func_get_args();
        if ($parameters) {
            $unique = false;

            if (count($parameters) === 3) {
                [$column, $values, $unique] = $parameters;
            } else {
                [$column, $values] = $parameters;
            }

            // Do batch push by default.
            $values = Arr::wrap($values);

            $query = $this->setKeysForSaveQuery($this->newQuery());

            $this->pushAttributeValues($column, $values, $unique);

            return $query->push($column, $values, $unique);
        }

        return parent::push();
    }

    /**
     * Remove one or more values from an array.
     *
     * @param  string $column
     * @param  mixed  $values
     *
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
     * @param  bool   $unique
     */
    protected function pushAttributeValues($column, array $values, $unique = false)
    {
        $current = $this->getAttributeFromArray($column) ?: [];

        foreach ($values as $value) {
            // Don't add duplicate values when we only want unique values.
            if ($unique && (! is_array($current) || in_array($value, $current))) {
                continue;
            }

            $current[] = $value;
        }

        $this->attributes[$column] = $current;

        $this->syncOriginalAttribute($column);
    }

    /**
     * Remove one or more values to the underlying attribute value and sync with original.
     *
     * @param  string $column
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

    /** @inheritdoc */
    public function getForeignKey()
    {
        return Str::snake(class_basename($this)) . '_' . ltrim($this->primaryKey, '_');
    }

    /**
     * Set the parent relation.
     */
    public function setParentRelation(Relation $relation)
    {
        $this->parentRelation = $relation;
    }

    /**
     * Get the parent relation.
     */
    public function getParentRelation(): ?Relation
    {
        return $this->parentRelation ?? null;
    }

    /** @inheritdoc */
    public function newEloquentBuilder($query)
    {
        return new Builder($query);
    }

    /** @inheritdoc */
    public function qualifyColumn($column)
    {
        return $column;
    }

    /** @inheritdoc */
    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();

        return new QueryBuilder($connection, $connection->getQueryGrammar(), $connection->getPostProcessor());
    }

    /** @inheritdoc */
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
                    $relations[] = $key . '.' . $collectionValue;
                }
            }

            if ($relation instanceof QueueableEntity) {
                foreach ($relation->getQueueableRelations() as $entityKey => $entityValue) {
                    $relations[] = $key . '.' . $entityValue;
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

        $parentRelation = $this->getParentRelation();
        if ($parentRelation) {
            unset($relations[$parentRelation->getQualifiedForeignKeyName()]);
        }

        return $relations;
    }

    /**
     * Checks if column exists on a table.  As this is a document model, just return true.  This also
     * prevents calls to non-existent function Grammar::compileColumnListing().
     *
     * @param  string $key
     *
     * @return bool
     */
    protected function isGuardableColumn($key)
    {
        return true;
    }

    /** @inheritdoc */
    protected function addCastAttributesToArray(array $attributes, array $mutatedAttributes)
    {
        foreach ($this->getCasts() as $key => $castType) {
            if (! Arr::has($attributes, $key) || Arr::has($mutatedAttributes, $key)) {
                continue;
            }

            $originalValue = Arr::get($attributes, $key);

            // Here we will cast the attribute. Then, if the cast is a date or datetime cast
            // then we will serialize the date for the array. This will convert the dates
            // to strings based on the date format specified for these Eloquent models.
            $castValue = $this->castAttribute(
                $key,
                $originalValue,
            );

            // If the attribute cast was a date or a datetime, we will serialize the date as
            // a string. This allows the developers to customize how dates are serialized
            // into an array without affecting how they are persisted into the storage.
            if ($castValue !== null && in_array($castType, ['date', 'datetime', 'immutable_date', 'immutable_datetime'])) {
                $castValue = $this->serializeDate($castValue);
            }

            if ($castValue !== null && ($this->isCustomDateTimeCast($castType) || $this->isImmutableCustomDateTimeCast($castType))) {
                $castValue = $castValue->format(explode(':', $castType, 2)[1]);
            }

            if ($castValue instanceof DateTimeInterface && $this->isClassCastable($key)) {
                $castValue = $this->serializeDate($castValue);
            }

            if ($castValue !== null && $this->isClassSerializable($key)) {
                $castValue = $this->serializeClassCastableAttribute($key, $castValue);
            }

            if ($this->isEnumCastable($key) && (! $castValue instanceof Arrayable)) {
                $castValue = $castValue !== null ? $this->getStorableEnumValueFromLaravel11($this->getCasts()[$key], $castValue) : null;
            }

            if ($castValue instanceof Arrayable) {
                $castValue = $castValue->toArray();
            }

            Arr::set($attributes, $key, $castValue);
        }

        return $attributes;
    }

    /**
     * Duplicate of {@see HasAttributes::getStorableEnumValue()} for Laravel 11 as the signature of the method has
     * changed in a non-backward compatible way.
     *
     * @todo Remove this method when support for Laravel 10 is dropped.
     */
    private function getStorableEnumValueFromLaravel11($expectedEnum, $value)
    {
        if (! $value instanceof $expectedEnum) {
            throw new ValueError(sprintf('Value [%s] is not of the expected enum type [%s].', var_export($value, true), $expectedEnum));
        }

        return $value instanceof BackedEnum
            ? $value->value
            : $value->name;
    }

    /**
     * Is a value a BSON type?
     *
     * @param mixed $value
     *
     * @return bool
     */
    protected function isBSON(mixed $value): bool
    {
        return $value instanceof Type;
    }

    /**
     * {@inheritDoc}
     */
    public function save(array $options = [])
    {
        // SQL databases would use autoincrement the id field if set to null.
        // Apply the same behavior to MongoDB with _id only, otherwise null would be stored.
        if (array_key_exists('_id', $this->attributes) && $this->attributes['_id'] === null) {
            unset($this->attributes['_id']);
        }

        $saved = parent::save($options);

        // Clear list of unset fields
        $this->unset = [];

        return $saved;
    }

    /**
     * {@inheritDoc}
     */
    public function refresh()
    {
        parent::refresh();

        // Clear list of unset fields
        $this->unset = [];

        return $this;
    }
}
