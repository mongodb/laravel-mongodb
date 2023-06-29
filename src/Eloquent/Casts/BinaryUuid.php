<?php

namespace Jenssegers\Mongodb\Eloquent\Casts;

use function bin2hex;
use function hex2bin;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Jenssegers\Mongodb\Eloquent\Model;
use MongoDB\BSON\Binary;
use function str_replace;
use function substr;

class BinaryUuid implements CastsAttributes
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
        if (! $value instanceof Binary || $value->getType() !== Binary::TYPE_UUID) {
            return $value;
        }

        $base16Uuid = bin2hex($value->getData());

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($base16Uuid, 0, 8),
            substr($base16Uuid, 8, 4),
            substr($base16Uuid, 12, 4),
            substr($base16Uuid, 16, 4),
            substr($base16Uuid, 20, 12),
        );
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
        if ($value instanceof Binary) {
            return $value;
        }

        if (is_string($value) && strlen($value) === 16) {
            return new Binary($value, Binary::TYPE_UUID);
        }

        return new Binary(hex2bin(str_replace('-', '', $value)), Binary::TYPE_UUID);
    }
}
