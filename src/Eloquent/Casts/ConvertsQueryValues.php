<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Eloquent\Casts;

interface ConvertsQueryValues
{
    /**
     * Convert a where-clause value to its native BSON type, so queries accept the
     * string representation exposed by the model, like "_id" queries do.
     *
     * The conversion is lenient: a value that cannot be converted is returned
     * unchanged, like the default "_id" heuristic does.
     */
    public static function convertQueryValue(mixed $value): mixed;
}
