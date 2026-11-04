<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Eloquent\Casts;

use Illuminate\Contracts\Database\Eloquent\Castable;
use MongoDB\BSON\ObjectId as BSONObjectId;

use function ctype_xdigit;
use function is_string;
use function strlen;

/**
 * Cast an attribute to an ObjectId and convert where-clause values, so queries
 * on this attribute accept the string representation exposed by the model:
 *
 *      protected $casts = [
 *          'commentable_id' => AsObjectId::class,
 *      ];
 *
 * Unlike the ObjectId cast, string values used in where() are converted to
 * ObjectId, matching documents where the attribute is stored as an ObjectId.
 */
class AsObjectId extends ObjectId implements Castable, ConvertsQueryValues
{
    public static function castUsing(array $arguments): static
    {
        return new static();
    }

    /**
     * Convert a 24-character hexadecimal string to an ObjectId. Any other value
     * is returned unchanged, so it matches nothing instead of throwing.
     */
    public static function convertQueryValue(mixed $value): mixed
    {
        if (is_string($value) && strlen($value) === 24 && ctype_xdigit($value)) {
            return new BSONObjectId($value);
        }

        return $value;
    }
}
