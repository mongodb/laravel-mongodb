<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Encryption;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\Int64;
use MongoDB\Laravel\Connection;
use stdClass;
use Stringable;

use function array_is_list;
use function array_key_exists;
use function array_shift;
use function class_exists;
use function ctype_digit;
use function explode;
use function filter_var;
use function in_array;
use function is_array;
use function is_int;
use function is_numeric;
use function is_scalar;
use function is_string;
use function ltrim;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strcmp;
use function strlen;
use function strtolower;
use function substr;

use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_BOOLEAN;

/**
 * Derives the Eloquent cast of an encrypted field from the bsonType declared
 * in the encryptedFieldsMap, and coerces the value to that BSON type on write.
 *
 * Automatic encryption rejects a value of another BSON type, and a value that
 * comes from a form or a JSON payload is a string, so it is coerced here
 * rather than stored as a string or rejected by the driver.
 *
 * @internal
 */
final class EncryptedFieldTypes
{
    /**
     * Eloquent cast derived from each bsonType. Null means the driver already
     * returns the correct native value, or no cast can store the type: a bare
     * "decimal" cast is invalid, the scale is mandatory.
     */
    private const CASTS = [
        'string' => null,
        'int' => 'integer',
        'long' => 'integer',
        'double' => 'float',
        'decimal' => null,
        'bool' => 'boolean',
        // "datetime" keeps the time of a BSON date, which "date" would truncate.
        'date' => 'datetime',
        'object' => null,
        'array' => null,
    ];

    /**
     * Native Eloquent casts accepted on a field of each bsonType. A cast
     * outside this list stores a value of another BSON type, which automatic
     * encryption rejects, so it is reported as a conflict.
     */
    private const COMPATIBLE_CASTS = [
        'string' => ['string'],
        'int' => ['integer', 'int'],
        'long' => ['integer', 'int'],
        'double' => ['float', 'double', 'real'],
        'decimal' => ['decimal'],
        'bool' => ['boolean', 'bool'],
        'date' => ['date', 'datetime', 'immutable_date', 'immutable_datetime', 'custom_datetime'],
        'object' => [],
        'array' => [],
    ];

    /** The largest value of a signed 64-bit integer, as text. */
    private const INT64_MAX = '9223372036854775807';

    /** The absolute value of the smallest signed 64-bit integer, as text. */
    private const INT64_MIN_ABS = '9223372036854775808';

    /**
     * The bsonType declared for each encrypted field of the collection of a
     * model, keyed by field path. Empty when the collection is not encrypted.
     *
     * @return array<string, string>
     */
    public static function fieldTypesFor(Model $model): array
    {
        // A model used without a database connection, as a DTO or in a unit
        // test, has no connection to resolve.
        if ($model::getConnectionResolver() === null) {
            return [];
        }

        $connection = $model->getConnection();

        if (! $connection instanceof Connection) {
            return [];
        }

        return $connection->encryptedFieldTypes($model->getTable());
    }

    /**
     * Merge the casts derived from the encryptedFieldsMap into the casts
     * declared on a model. A declared cast is kept, but reported when it cannot
     * store the declared bsonType.
     *
     * @param class-string          $model
     * @param array<string, mixed>  $declaredCasts
     * @param array<string, string> $fieldTypes    Map of field path to bsonType.
     *
     * @return array<string, mixed>
     *
     * @throws LogicException
     */
    public static function mergeCasts(string $model, array $declaredCasts, array $fieldTypes): array
    {
        $casts = $declaredCasts;

        foreach ($fieldTypes as $path => $bsonType) {
            // An Eloquent cast is keyed by a plain attribute name, never a
            // dotted sub-path.
            if (str_contains($path, '.')) {
                continue;
            }

            if (array_key_exists($path, $casts)) {
                if (! self::isCompatible($bsonType, (string) $casts[$path])) {
                    throw new LogicException(sprintf(
                        'The "%s" cast declared on attribute "%s" of model "%s" does not store the "%s" BSON type declared for this encrypted field. Remove the cast, or declare a compatible one.',
                        $casts[$path],
                        $path,
                        $model,
                        $bsonType,
                    ));
                }

                continue;
            }

            $cast = self::CASTS[$bsonType] ?? null;
            if ($cast !== null) {
                $casts[$path] = $cast;
            }
        }

        return $casts;
    }

    /**
     * Whether a declared cast is a custom cast class, which owns the value it
     * stores. Laravel's isClassCastable() is protected, so the class check is
     * repeated here; the arguments after the colon are not part of the name.
     */
    public static function isCustomCast(mixed $cast): bool
    {
        return is_string($cast) && class_exists(explode(':', $cast, 2)[0]);
    }

    /** Whether a cast declared by the developer can store the declared bsonType. */
    private static function isCompatible(string $bsonType, string $cast): bool
    {
        // A custom cast class is the developer's responsibility.
        if (self::isCustomCast($cast)) {
            return true;
        }

        [$type, $scale] = explode(':', strtolower($cast), 2) + [1 => null];

        // A decimal cast requires an integer scale: without one, the value
        // cannot be converted, so the cast cannot store the declared BSON type.
        if ($type === 'decimal' && ($scale === null || ! ctype_digit($scale))) {
            return false;
        }

        return in_array($type, self::COMPATIBLE_CASTS[$bsonType] ?? [], true);
    }

    /**
     * Coerce a value assigned to an attribute to the BSON type declared for
     * that field. When the attribute is an embedded document holding encrypted
     * sub-fields declared as dotted paths, the sub-values are coerced too.
     *
     * @param array<string, string> $fieldTypes    Map of field path to bsonType.
     * @param callable|null         $dateConverter Converts a value to the date
     *                                             type of the model, applied to
     *                                             a "date" field.
     * @param array<string, true>   $skipPaths     Sub-paths of the embedded
     *                                             document whose value is owned
     *                                             by a custom cast of the
     *                                             nested model that wrote it.
     */
    public static function coerceField(array $fieldTypes, string $key, mixed $value, ?callable $dateConverter = null, array $skipPaths = []): mixed
    {
        if ($value === null) {
            return null;
        }

        if (array_key_exists($key, $fieldTypes)) {
            return self::coerce($fieldTypes[$key], $value, $dateConverter);
        }

        // The attribute is not encrypted itself, but it may hold encrypted
        // sub-fields, declared as "parent.child" paths.
        $prefix = $key . '.';
        $children = [];
        foreach ($fieldTypes as $path => $bsonType) {
            if (str_starts_with($path, $prefix)) {
                $children[substr($path, strlen($prefix))] = $bsonType;
            }
        }

        if ($children === []) {
            return $value;
        }

        return self::coerceEmbedded($children, $value, $dateConverter, $skipPaths);
    }

    /**
     * Coerce a value to the native BSON type declared for the field.
     *
     * A value that cannot be converted is returned unchanged, so automatic
     * encryption still rejects it with its own message rather than silently
     * storing a corrupted value.
     */
    private static function coerce(string $bsonType, mixed $value, ?callable $dateConverter = null): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($bsonType) {
            'int' => is_numeric($value) ? (int) $value : $value,
            // The driver stores a PHP int as int32 when it fits, so wrap it to
            // force the int64 stored for a "long" field.
            'long' => is_numeric($value) ? self::toInt64($value) : $value,
            'double' => is_numeric($value) ? (float) $value : $value,
            'decimal' => $value instanceof Decimal128 || ! is_numeric($value) ? $value : new Decimal128((string) $value),
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $value,
            'date' => $dateConverter === null ? $value : $dateConverter($value),
            'string' => is_scalar($value) || $value instanceof Stringable ? (string) $value : $value,
            // Only a plain object is converted: any other object is a value of
            // its own and is left untouched.
            'object' => $value instanceof stdClass ? self::toDocumentArray($value) : $value,
            default => $value,
        };
    }

    /**
     * The value as a driver Int64. A value outside the signed 64-bit range is
     * returned unchanged: the (int) cast would saturate it to PHP_INT_MAX and
     * the driver would store a different number.
     */
    private static function toInt64(mixed $value): mixed
    {
        if (self::isOutsideInt64Range($value)) {
            return $value;
        }

        return new Int64((int) $value);
    }

    /**
     * Whether a number is outside the signed 64-bit range. An integer written
     * as digits is compared as text, because a float cannot tell PHP_INT_MAX
     * from PHP_INT_MAX + 1. Any other number is compared as a float, with an
     * exclusive bound because 2^63 is not a PHP int.
     */
    private static function isOutsideInt64Range(mixed $value): bool
    {
        if (is_int($value)) {
            return false;
        }

        if (is_string($value) && preg_match('/^[+-]?\d+$/', $value) === 1) {
            $limit  = str_starts_with($value, '-') ? self::INT64_MIN_ABS : self::INT64_MAX;
            $digits = ltrim(ltrim($value, '+-'), '0');

            return strlen($digits) > strlen($limit)
                || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0);
        }

        $number = (float) $value;

        return $number >= 2 ** 63 || $number < -(2 ** 63);
    }

    /**
     * The object as an array, or the object itself when the array form would be
     * encoded as a BSON array: automatic encryption rejects that for a field
     * declared as a document.
     *
     * @return array<array-key, mixed>|stdClass
     */
    private static function toDocumentArray(stdClass $value): array|stdClass
    {
        $array = (array) $value;

        return array_is_list($array) ? $value : $array;
    }

    /**
     * Coerce the encrypted sub-values of an embedded document. The document is
     * walked natively, never JSON-encoded, so automatic encryption still sees
     * the declared BSON types.
     *
     * @param array<string, string> $fieldTypes Map of sub-path to bsonType,
     *                                          relative to the embedded document.
     * @param array<string, true>   $skipPaths  Sub-paths owned by a custom cast.
     */
    private static function coerceEmbedded(array $fieldTypes, mixed $value, ?callable $dateConverter, array $skipPaths = []): mixed
    {
        if ($value instanceof stdClass) {
            $value = self::toDocumentArray($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        foreach ($fieldTypes as $path => $bsonType) {
            if (isset($skipPaths[$path])) {
                continue;
            }

            $value = self::coercePath($value, explode('.', $path), $bsonType, $dateConverter);
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $value
     * @param list<string>            $segments The remaining path segments.
     *
     * @return array<array-key, mixed>
     */
    private static function coercePath(array $value, array $segments, string $bsonType, ?callable $dateConverter): array
    {
        $segment = array_shift($segments);

        if (! array_key_exists($segment, $value)) {
            return $value;
        }

        if ($segments === []) {
            $value[$segment] = self::coerce($bsonType, $value[$segment], $dateConverter);

            return $value;
        }

        $nested = $value[$segment];

        if ($nested instanceof stdClass) {
            $nested = self::toDocumentArray($nested);
        }

        if (is_array($nested)) {
            $value[$segment] = self::coercePath($nested, $segments, $bsonType, $dateConverter);
        }

        return $value;
    }
}
