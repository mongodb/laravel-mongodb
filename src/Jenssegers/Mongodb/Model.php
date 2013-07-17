<?php namespace Jenssegers\Mongodb;

use Illuminate\Database\Eloquent\Collection;
use Jenssegers\Mongodb\DatabaseManager as Resolver;
use Jenssegers\Mongodb\Builder as QueryBuilder;

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
    protected function fromDateTime($value)
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