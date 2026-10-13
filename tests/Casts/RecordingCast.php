<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A custom cast that records the value it receives, to check that a custom cast
 * owns its value: the encrypted field coercion must not run on it.
 *
 * @implements CastsAttributes<mixed, mixed>
 */
final class RecordingCast implements CastsAttributes
{
    public static mixed $received = null;

    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        self::$received = $value;

        return $value;
    }
}
