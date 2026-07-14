<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Eloquent\Casts;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use MongoDB\BSON\Document;
use MongoDB\Model\BSONArray;

use function is_array;

class AsBsonArray implements Castable, CastsAttributes
{
    public static function castUsing(array $arguments): static
    {
        return new static();
    }

    public function get($model, string $key, $value, array $attributes)
    {
        if ($value instanceof BSONArray) {
            return clone $value;
        }

        return is_array($value) ? new BSONArray($value) : null;
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if ($value === null) {
            return [$key => null];
        }

        if ($value instanceof BSONArray) {
            return [$key => clone $value];
        }

        return [$key => new BSONArray((array) $value)];
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

        if ($value instanceof BSONArray) {
            $value = $value->getArrayCopy();
        }

        return (string) Document::fromPHP(['v' => (array) $value]);
    }
}
