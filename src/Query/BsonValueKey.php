<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Query;

use InvalidArgumentException;
use MongoDB\BSON\Document;
use MongoDB\BSON\Type;
use Stringable;

use function get_debug_type;
use function is_float;
use function is_scalar;
use function sprintf;

/** @internal */
final class BsonValueKey
{
    public static function of(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // The string conversion of a double rounds it to the "precision" ini
        // setting, so the driver encodes it to keep the exact value.
        if ($value instanceof Type || is_float($value)) {
            return Document::fromPHP(['key' => $value])->toCanonicalExtendedJSON();
        }

        if (is_scalar($value) || $value instanceof Stringable) {
            return get_debug_type($value) . ':' . $value;
        }

        throw new InvalidArgumentException(sprintf(
            'A value of type "%s" cannot be used as a document key.',
            get_debug_type($value),
        ));
    }
}
