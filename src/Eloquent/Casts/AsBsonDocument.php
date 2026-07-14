<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Eloquent\Casts;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use MongoDB\BSON\Document;
use MongoDB\Model\BSONDocument;
use stdClass;

use function is_array;

class AsBsonDocument implements Castable, CastsAttributes
{
    public static function castUsing(array $arguments): static
    {
        return new static();
    }

    public function get($model, string $key, $value, array $attributes)
    {
        if ($value instanceof BSONDocument) {
            return clone $value;
        }

        if ($value instanceof stdClass) {
            $value = (array) $value;
        }

        return is_array($value) ? new BSONDocument($value) : null;
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if ($value === null) {
            return [$key => null];
        }

        if ($value instanceof BSONDocument) {
            return [$key => clone $value];
        }

        return [$key => new BSONDocument((array) $value)];
    }

    public function compare($model, string $key, $original, $value): bool
    {
        return self::normalize($original) === self::normalize($value);
    }

    private static function normalize($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof BSONDocument) {
            $value = $value->getArrayCopy();
        } elseif ($value instanceof stdClass) {
            $value = (array) $value;
        }

        return (string) Document::fromPHP(['v' => $value]);
    }
}
