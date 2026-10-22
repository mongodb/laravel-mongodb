<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Encryption;

use MongoDB\BSON\Document;
use MongoDB\BSON\Int64;

use function array_flip;
use function array_intersect_key;
use function array_is_list;
use function array_keys;
use function array_map;
use function array_unique;
use function is_array;
use function is_string;
use function ksort;
use function sort;

/**
 * The state of a collection declared in the encryptedFieldsMap, with the paths
 * of the encrypted fields that differ from the ones stored with the collection.
 *
 * @internal
 */
final readonly class CollectionStatus
{
    /**
     * The query options that are compared. An option that the configuration
     * does not control is ignored, so a value added by libmongocrypt does not
     * read as a difference. An option that a later server version adds is not
     * compared until it is listed here.
     */
    private const QUERY_OPTIONS = ['queryType', 'contention', 'min', 'max', 'sparsity', 'precision', 'trimFactor'];

    /** The contention stored for a query when the configuration does not declare one. */
    private const DEFAULT_CONTENTION = 8;

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
     * The data keys are not compared: the configuration names them with a
     * keyAltName, while the collection stores the resolved keyId.
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
        $declaredSpecs = self::fieldSpecs($declared);
        $storedSpecs = self::fieldSpecs($stored);

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
     * A canonical representation of each field, keyed by path, so that two
     * declarations are equal when they encrypt the same path the same way,
     * whatever the order of the fields and of their queries.
     *
     * @param  array<mixed> $fields
     *
     * @return array<string, string>
     */
    private static function fieldSpecs(array $fields): array
    {
        $specs = [];

        foreach ($fields as $field) {
            if (! is_array($field) || ! isset($field['path']) || ! is_string($field['path'])) {
                continue;
            }

            $specs[$field['path']] = Document::fromPHP([
                'bsonType' => $field['bsonType'] ?? null,
                'queries' => self::querySpecs($field['queries'] ?? null),
            ])->toCanonicalExtendedJSON();
        }

        return $specs;
    }

    /**
     * The canonical representation of the queries of a field, sorted so that
     * their order is not significant.
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
            $query = array_intersect_key($query, array_flip(self::QUERY_OPTIONS));

            $query = array_map(self::normalizeValue(...), $query);

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
