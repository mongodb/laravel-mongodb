<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Encryption;

use MongoDB\BSON\Decimal128;
use MongoDB\BSON\Document;
use MongoDB\BSON\Int64;

use function array_is_list;
use function array_keys;
use function array_map;
use function array_unique;
use function is_array;
use function is_float;
use function is_string;
use function ksort;
use function preg_match;
use function sort;

/**
 * The state of a collection declared in the encryptedFieldsMap, with the paths
 * of the encrypted fields that differ from the ones stored with the collection.
 *
 * @internal
 */
final readonly class CollectionStatus
{
    /** The contention stored for a query when the configuration does not declare one. */
    private const DEFAULT_CONTENTION = 8;

    /** The query options the server stores as an integer, whatever the declared type. */
    private const INTEGER_OPTIONS = [
        'contention',
        'sparsity',
        'precision',
        'trimFactor',
        // The string preview lengths, which the server stores as an integer
        // even when the configuration declares a double or a decimal.
        'strMinQueryLength',
        'strMaxQueryLength',
        'strMaxLength',
    ];

    /** @param list<string> $differingFields */
    public function __construct(
        public CollectionState $state,
        public array $differingFields = [],
    ) {
    }

    /**
     * The status of a collection that holds encrypted fields, compared with the
     * fields declared in the configuration.
     *
     * The data key is compared only when the configuration declares a keyId:
     * a keyAltName is resolved by the server, which stores the resolved keyId.
     *
     * @param list<array<string, mixed>> $declared
     * @param array<string, mixed>       $stored   The "encryptedFields" option of the collection.
     */
    public static function fromEncryptedFields(array $declared, array $stored): self
    {
        $fields = $stored['fields'] ?? null;
        $differing = self::differingFields($declared, is_array($fields) ? $fields : []);

        return new self(
            $differing === [] ? CollectionState::Encrypted : CollectionState::Mismatched,
            $differing,
        );
    }

    /**
     * Whether the collection has to be dropped and created again to hold the
     * declared encrypted fields. A collection that does not exist yet, or that
     * already holds them, does not.
     */
    public function needsRecreate(): bool
    {
        return $this->state === CollectionState::NotEncrypted || $this->state === CollectionState::Mismatched;
    }

    /**
     * The paths of the fields whose declaration differs from the stored one,
     * including the paths declared on one side only.
     *
     * @param list<array<string, mixed>> $declared
     * @param array<mixed>               $stored
     *
     * @return list<string>
     */
    private static function differingFields(array $declared, array $stored): array
    {
        $keyIdPaths = self::keyIdPaths($declared);

        $declaredSpecs = self::fieldSpecs($declared, $keyIdPaths);
        $storedSpecs = self::fieldSpecs($stored, $keyIdPaths);

        $differing = [];

        foreach (array_unique([...array_keys($declaredSpecs), ...array_keys($storedSpecs)]) as $path) {
            if (($declaredSpecs[$path] ?? null) !== ($storedSpecs[$path] ?? null)) {
                $differing[] = $path;
            }
        }

        sort($differing);

        return $differing;
    }

    /**
     * The paths whose declared field names the data key with a keyId, the only
     * key that can be compared: a keyAltName is resolved by the server, which
     * stores the resolved keyId, so it cannot be checked against the
     * configuration.
     *
     * @param  list<array<string, mixed>> $declared
     *
     * @return array<string, true>
     */
    private static function keyIdPaths(array $declared): array
    {
        $paths = [];

        foreach ($declared as $field) {
            if (is_array($field) && isset($field['path']) && is_string($field['path']) && isset($field['keyId'])) {
                $paths[$field['path']] = true;
            }
        }

        return $paths;
    }

    /**
     * A canonical representation of each field, keyed by path, so that two
     * declarations are equal when they encrypt the same path the same way,
     * whatever the order of the fields and of their queries.
     *
     * Every option of a field is compared, so an option that a later server
     * version adds is compared without a change here.
     *
     * @param array<mixed>        $fields
     * @param array<string, true> $keyIdPaths The paths whose data key is compared.
     *
     * @return array<string, string>
     */
    private static function fieldSpecs(array $fields, array $keyIdPaths): array
    {
        $specs = [];

        foreach ($fields as $field) {
            if (! is_array($field) || ! isset($field['path']) || ! is_string($field['path'])) {
                continue;
            }

            $path = $field['path'];

            // The path names the field and the keyAltName is not stored.
            if (! isset($keyIdPaths[$path])) {
                unset($field['keyId']);
            }

            unset($field['path'], $field['keyAltName']);

            $field['queries'] = self::querySpecs($field['queries'] ?? null);

            ksort($field);

            $specs[$path] = Document::fromPHP($field)->toCanonicalExtendedJSON();
        }

        return $specs;
    }

    /**
     * The canonical representation of the queries of a field, sorted so that
     * their order is not significant.
     *
     * Every option of a query is compared, so an option that a later server
     * version adds is compared without a change here. An option the server
     * stores on its own, such as the contention below, has to be given its
     * default here, otherwise every collection reads as a difference.
     *
     * @return list<string>
     */
    private static function querySpecs(mixed $queries): array
    {
        if (! is_array($queries)) {
            return [];
        }

        // A field can declare a single query document where a list of queries
        // is expected, and the collection stores the shape it was given.
        if (! array_is_list($queries)) {
            $queries = [$queries];
        }

        $specs = [];

        foreach ($queries as $query) {
            if (! is_array($query)) {
                continue;
            }

            // The collection stores the contention of every query, whether the
            // configuration declares one or not.
            $query['contention'] ??= self::DEFAULT_CONTENTION;

            $query = array_map(self::normalizeValue(...), $query);

            // The server stores an integer option declared as a float or a
            // decimal as an integer, such as contention 4.0 or Decimal128("4").
            foreach (self::INTEGER_OPTIONS as $option) {
                $value = $query[$option] ?? null;

                if ((is_float($value) || $value instanceof Decimal128) && preg_match('/^-?\d+$/', (string) $value) === 1) {
                    $query[$option] = (int) (string) $value;
                }
            }

            ksort($query);

            // The BSON encoding compares the values as the types the server
            // stores, and does not round a double like json_encode() does.
            $specs[] = Document::fromPHP($query)->toCanonicalExtendedJSON();
        }

        sort($specs);

        return $specs;
    }

    /**
     * A query option value as the driver reads it back.
     *
     * The bound of a "long" range field has to be declared as an Int64, because
     * the server rejects a PHP int there, while a stored 64-bit integer that
     * fits in a PHP int is read back as an int. The two represent the same
     * bound, so they have to compare equal.
     */
    private static function normalizeValue(mixed $value): mixed
    {
        if (! $value instanceof Int64) {
            return $value;
        }

        $int = (int) (string) $value;

        return (string) $int === (string) $value ? $int : $value;
    }
}
