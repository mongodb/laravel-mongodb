<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Eloquent\Casts;

use Illuminate\Contracts\Database\Eloquent\Castable;
use MongoDB\BSON\Binary;

use function hex2bin;
use function is_string;
use function preg_match;
use function str_replace;
use function strlen;

/**
 * Cast an attribute to a Binary UUID and convert where-clause values, so queries
 * on this attribute accept the string representation exposed by the model:
 *
 *      protected $casts = [
 *          'device_token' => AsBinaryUuid::class,
 *      ];
 *
 * Unlike the BinaryUuid cast, UUID strings used in where() are converted to
 * a UUID Binary, matching documents where the attribute is stored as a UUID.
 */
class AsBinaryUuid extends BinaryUuid implements Castable, ConvertsQueryValues
{
    public static function castUsing(array $arguments): static
    {
        return new static();
    }

    /**
     * Convert a UUID string to a UUID Binary, accepting the same representations
     * as the BinaryUuid cast: 16 raw bytes, or hexadecimal with or without dashes.
     * A 16-character printable string is kept as-is, so legacy string values
     * keep matching. Any other value is returned unchanged.
     */
    public static function convertQueryValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        if (strlen($value) === 16 && preg_match('~[^\x20-\x7E\t\r\n]~', $value) > 0) {
            return new Binary($value, Binary::TYPE_UUID);
        }

        if (preg_match('/^[0-9a-f]{8}(-?)[0-9a-f]{4}\1[0-9a-f]{4}\1[0-9a-f]{4}\1[0-9a-f]{12}$/iD', $value)) {
            return new Binary(hex2bin(str_replace('-', '', $value)), Binary::TYPE_UUID);
        }

        return $value;
    }
}
