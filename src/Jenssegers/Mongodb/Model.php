<?php namespace Jenssegers\Mongodb;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Jenssegers\Mongodb\DatabaseManager as Resolver;
use Jenssegers\Mongodb\Query\Builder as QueryBuilder;
use Jenssegers\Mongodb\Relations\BelongsTo;
use Jenssegers\Mongodb\Relations\BelongsToMany;

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

        return $this->getKey();
    }

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
            /**
             * MongoIds are converted to string to make it easier to pass
             * the id to other instances or relations.
             */
            if ($value instanceof MongoId)
            {
                $value = (string) $value;
            }
        }

        parent::setRawAttributes($attributes, $sync);
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
                $value = $this->asDateTime($value)->format('Y-m-d H:i:s');
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
