<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Encryption\Commands;

use InvalidArgumentException;
use MongoDB\Driver\Exception\Exception as DriverException;
use MongoDB\Laravel\Connection;
use MongoDB\Laravel\Encryption\CollectionState;
use MongoDB\Laravel\Encryption\CollectionStatus;

use function array_keys;
use function count;
use function implode;
use function is_array;
use function is_string;
use function sprintf;

/**
 * Helpers shared by the Queryable Encryption CLI tools.
 *
 * @internal
 */
trait InteractsWithEncryption
{
    /**
     * Resolve the MongoDB connection referenced by the --connection option.
     */
    protected function connection(): Connection
    {
        $database = $this->getLaravel()->make('db');
        $name = $this->option('connection');
        $connection = $name ? $database->connection($name) : $database->connection();

        if (! $connection instanceof Connection) {
            throw new InvalidArgumentException(
                sprintf('The "%s" connection is not a MongoDB connection.', is_string($name) ? $name : 'default'),
            );
        }

        return $connection;
    }

    /**
     * Get the driver_options.autoEncryption configuration, or fail if Queryable
     * Encryption is not configured on the connection.
     *
     * @return array<string, mixed>
     */
    protected function autoEncryptionConfig(Connection $connection): array
    {
        $config = $connection->getConfig('driver_options.autoEncryption');

        if (! is_array($config)) {
            throw new InvalidArgumentException('Queryable Encryption is not enabled on this connection. Configure "driver_options.autoEncryption" with a "keyVaultNamespace" and "kmsProviders" first.');
        }

        return $config;
    }

    /**
     * The state of the given mapped collections on the server.
     *
     * The names are the logical collection names: the connection table prefix
     * is applied. A single listCollections call reads every collection, and its
     * encryptedFields option tells a collection that exists without encryption,
     * and an encrypted collection whose fields are not the declared ones, from
     * a collection that holds the declared fields.
     *
     * @param  array<string, list<array<string, mixed>>> $encryptedFields The encrypted fields declared for each collection.
     *
     * @return array<string, CollectionStatus> Keyed by the logical collection name.
     *
     * @throws DriverException
     */
    protected function collectionStates(Connection $connection, array $encryptedFields): array
    {
        $prefix = $connection->getTablePrefix();
        $realNames = [];
        $states = [];

        foreach (array_keys($encryptedFields) as $collection) {
            $realNames[$prefix . $collection] = $collection;
            $states[$collection] = new CollectionStatus(CollectionState::Missing);
        }

        foreach ($connection->getDatabase()->listCollections(['filter' => ['name' => ['$in' => array_keys($realNames)]]]) as $info) {
            $collection = $realNames[$info->getName()] ?? null;

            if ($collection === null) {
                continue;
            }

            $stored = $info->getOptions()['encryptedFields'] ?? null;

            $states[$collection] = is_array($stored)
                ? CollectionStatus::fromEncryptedFields($encryptedFields[$collection], $stored)
                : new CollectionStatus(CollectionState::NotEncrypted);
        }

        return $states;
    }

    /**
     * Print each mapped collection with its state on the server, and return the
     * ones that do not hold the declared fields.
     *
     * A collection in one of those states is printed as an error: it cannot be
     * fixed in place, it has to be dropped and created again.
     *
     * @param  array<string, list<array<string, mixed>>> $encryptedFields
     * @param  array<string, CollectionStatus>|null      $states          Null to list the collections without their state.
     *
     * @return array<string, CollectionStatus> The collections that do not hold the declared fields.
     */
    protected function printCollectionStates(array $encryptedFields, ?array $states): array
    {
        if ($encryptedFields === []) {
            $this->line('Mapped collections: none');

            return [];
        }

        $this->line('Mapped collections: ');
        $problems = [];

        foreach ($encryptedFields as $collection => $fields) {
            $status = $states[$collection] ?? null;
            $label = $status === null ? '' : ', ' . $status->state->value;
            $line = sprintf('  - %s: %d encrypted field(s)%s', $collection, count($fields), $label);

            if ($status !== null && $status->needsRecreate()) {
                $problems[$collection] = $status;
                $this->error($line);
            } else {
                $this->line($line);
            }
        }

        return $problems;
    }

    /**
     * The message that tells how to bring a mapped collection back to the
     * fields declared in the configuration.
     *
     * A collection that exists without encryption, or with other encrypted
     * fields, cannot be fixed in place: it has to be dropped and created again,
     * which removes its documents, hence the instruction to migrate the data
     * first.
     */
    protected function remediation(string $collection, CollectionStatus $status): string
    {
        return sprintf(
            'The collection "%s" %s. Migrate the data, then create the collection again with "php artisan mongodb:encryption:create-collection %s --recreate%s".',
            $collection,
            $status->state === CollectionState::Mismatched
                ? sprintf('exists with encrypted fields that differ from the configuration ("%s")', implode('", "', $status->differingFields))
                : 'exists without encryption: its mapped fields are stored in plaintext',
            $collection,
            $this->connectionOption(),
        );
    }

    /** The --connection option to repeat in a remediation command, empty for the default connection. */
    protected function connectionOption(): string
    {
        $name = $this->option('connection');

        return $name ? ' --connection=' . $name : '';
    }
}
