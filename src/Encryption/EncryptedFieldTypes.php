<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Encryption;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\CastsInboundAttributes;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\Int64;
use MongoDB\Driver\Exception\InvalidArgumentException;
use MongoDB\Laravel\Connection;
use stdClass;
use Stringable;

use function array_is_list;
use function array_key_exists;
use function array_keys;
use function array_shift;
use function ctype_digit;
use function enum_exists;
use function explode;
use function filter_var;
use function in_array;
use function is_array;
use function is_int;
use function is_numeric;
use function is_scalar;
use function is_string;
use function is_subclass_of;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;
use function trim;

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
        'string' => ['string', 'hashed', 'encrypted'],
        'int' => ['integer', 'int'],
        'long' => ['integer', 'int'],
        'double' => ['float', 'double', 'real'],
        'decimal' => ['decimal'],
        'bool' => ['boolean', 'bool'],
        'date' => ['date', 'datetime', 'immutable_date', 'immutable_datetime', 'custom_datetime'],
        'object' => [],
        'array' => [],
    ];

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

        return $connection->encryption()->encryptedFieldTypes($model->getTable());
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
                $cast = $casts[$path];

                // Only a cast name is compared to the BSON type: another value
                // is a cast of the developer, which owns the value it stores.
                if (is_string($cast) && ! self::isCompatible($bsonType, $cast)) {
                    throw new LogicException(sprintf(
                        'The "%s" cast declared on attribute "%s" of model "%s" does not store the "%s" BSON type declared for this encrypted field. Remove the cast, or declare a compatible one.',
                        $cast,
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
     * Whether a declared cast is a cast class of the developer, which owns the
     * value it stores. Laravel's isClassCastable() is protected, so the check
     * is repeated here: a cast class implements one of the cast contracts, or
     * is an enum. A native type is never a cast class, even when a global class
     * has the same name, as "datetime" and DateTime.
     */
    public static function isCustomCast(mixed $cast): bool
    {
        if (! is_string($cast)) {
            return false;
        }

        $type = explode(':', $cast, 2)[0];

        return enum_exists($type)
            || is_subclass_of($type, CastsAttributes::class)
            || is_subclass_of($type, CastsInboundAttributes::class)
            || is_subclass_of($type, Castable::class);
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
            'decimal' => $value instanceof Decimal128 || ! is_numeric($value) ? $value : self::toDecimal128($value),
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
     * The value as a driver Int64. A value that is not an integer of the signed
     * 64-bit range is returned unchanged: the driver stores it as is, and
     * automatic encryption rejects it rather than a different number being
     * stored.
     */
    private static function toInt64(mixed $value): mixed
    {
        // A PHP int is always in the 64-bit range, whatever its size.
        if (is_int($value)) {
            return new Int64($value);
        }

        // Only a string is parsed: the driver rejects a float rather than a
        // rounded number being stored.
        if (! is_string($value)) {
            return $value;
        }

        try {
            // The constructor parses the string in 64 bits whatever the size of
            // a PHP int, and rejects a value out of range that the (int) cast
            // would saturate.
            return new Int64(trim($value));
        } catch (InvalidArgumentException) {
            return $value;
        }
    }

    /**
     * The value as a driver Decimal128. A value that the driver does not parse
     * is returned unchanged: automatic encryption rejects it rather than a
     * different number being stored.
     */
    private static function toDecimal128(mixed $value): mixed
    {
        try {
            // The constructor rejects a value surrounded by whitespace, which
            // is_numeric() accepts.
            return new Decimal128(trim((string) $value));
        } catch (InvalidArgumentException) {
            return $value;
        }
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
            if (self::isOwnedByCustomCast($skipPaths, $path)) {
                continue;
            }

            $value = self::coercePath($value, explode('.', $path), $bsonType, $dateConverter);
        }

        return $value;
    }

    /**
     * Whether a sub-path belongs to a value owned by a custom cast, either the
     * value itself or a document that cast returned.
     *
     * @param array<string, true> $skipPaths
     */
    private static function isOwnedByCustomCast(array $skipPaths, string $path): bool
    {
        foreach (array_keys($skipPaths) as $skipPath) {
            if ($path === $skipPath || str_starts_with($path, $skipPath . '.')) {
                return true;
            }
        }

        return false;
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
