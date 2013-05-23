<?php namespace Jenssegers\Mongodb;

use Illuminate\Database\ConnectionResolverInterface as Resolver;
use Illuminate\Database\Eloquent\Collection;
use Jenssegers\Mongodb\Query as QueryBuilder;

use DateTime;
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
     * Convert a DateTime to a storable string.
     *
     * @param  DateTime  $value
     * @return MongoDate
     */
    protected function fromDateTime(DateTime $value)
    {
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
     * Get a new query builder instance for the connection.
     *
     * @return Builder
     */
    protected function newBaseQueryBuilder()
    {
        $connection = $this->getConnection();
        return new QueryBuilder($connection);
    }

}