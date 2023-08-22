<?php

namespace MongoDB\Laravel\Eloquent\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use MongoDB\BSON\ObjectId as BSONObjectId;
use MongoDB\Laravel\Eloquent\Model;

class ObjectId implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return mixed
     */
    public function get($model, string $key, $value, array $attributes)
    {
        if (! $value instanceof BSONObjectId) {
            return $value;
        }

        return (string) $value;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  Model  $model
     * @param  string  $key
     * @param  mixed  $value
     * @param  array  $attributes
     * @return mixed
     */
    public function set($model, string $key, $value, array $attributes)
    {
        if ($value instanceof BSONObjectId) {
            return $value;
        }

        return new BSONObjectId($value);
    }
}
