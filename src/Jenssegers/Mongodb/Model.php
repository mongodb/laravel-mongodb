<?php namespace Jenssegers\Mongodb;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Jenssegers\Mongodb\DatabaseManager as Resolver;
use Jenssegers\Mongodb\Builder as QueryBuilder;
use Jenssegers\Mongodb\Relations\BelongsTo;

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
     * Convert a DateTime to a storable string.
     *
     * @param  DateTime|int  $value
     * @return string
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
        if ($value instanceof MongoDate)
        {
            $value = $value->sec;
        }

        if (is_int($value))
        {
            $value = "@$value";
        }

        return new DateTime($value);
    }

    /**
     * Get a fresh timestamp for the model.
     *
     * @return DateTime
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
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function hasOne($related, $foreignKey = null)
    {
        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $instance = new $related;

        return new HasOne($instance->newQuery(), $this, $foreignKey);
    }

    /**
     * Define a one-to-many relationship.
     *
     * @param  string  $related
     * @param  string  $foreignKey
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function hasMany($related, $foreignKey = null)
    {
        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $instance = new $related;

        return new HasMany($instance->newQuery(), $this, $foreignKey);
    }

    /**
     * Define an inverse one-to-one or many relationship.
     *
     * @param  string  $related
     * @param  string  $foreignKey
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function belongsTo($related, $foreignKey = null)
    {
        list(, $caller) = debug_backtrace(false);

        // If no foreign key was supplied, we can use a backtrace to guess the proper
        // foreign key name by using the name of the relationship function, which
        // when combined with an "_id" should conventionally match the columns.
        $relation = $caller['function'];

        if (is_null($foreignKey))
        {
            $foreignKey = snake_case($relation).'_id';
        }

        // Once we have the foreign key names, we'll just create a new Eloquent query
        // for the related models and returns the relationship instance which will
        // actually be responsible for retrieving and hydrating every relations.
        $instance = new $related;

        $query = $instance->newQuery();

        return new BelongsTo($query, $this, $foreignKey, $relation);
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
            // Convert MongoId
            if ($value instanceof MongoId)
            {
                $value = (string) $value;
            }

            // Convert MongoDate
            else if ($value instanceof MongoDate)
            {
                $value = $this->asDateTime($value)->format('Y-m-d H:i:s');
            }
        }

        parent::setRawAttributes($attributes, $sync);
    }

}