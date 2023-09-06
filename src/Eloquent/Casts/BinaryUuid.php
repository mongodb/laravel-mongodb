<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Eloquent\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use MongoDB\BSON\Binary;
use MongoDB\Laravel\Eloquent\Model;

use function bin2hex;
use function hex2bin;
use function is_string;
use function sprintf;
use function str_replace;
use function strlen;
use function substr;

class BinaryUuid implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  Model $model
     * @param  mixed $value
     *
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
     * @param  Model $model
     * @param  mixed $value
     *
     * @return Binary
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
