<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Encryption\Commands;

use InvalidArgumentException;
use MongoDB\Driver\Exception\Exception as DriverException;
use MongoDB\Laravel\Connection;
use MongoDB\Laravel\Encryption\CollectionState;

use function array_keys;
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
     * encryptedFields option tells an encrypted collection from a collection
     * that exists without encryption.
     *
     * @param  list<string> $collections
     *
     * @return array<string, CollectionState> Keyed by the logical collection name.
     *
     * @throws DriverException
     */
    protected function collectionStates(Connection $connection, array $collections): array
    {
        $prefix = $connection->getTablePrefix();
        $realNames = [];
        $states = [];

        foreach ($collections as $collection) {
            $realNames[$prefix . $collection] = $collection;
            $states[$collection] = CollectionState::Missing;
        }

        foreach ($connection->getDatabase()->listCollections(['filter' => ['name' => ['$in' => array_keys($realNames)]]]) as $info) {
            $collection = $realNames[$info->getName()] ?? null;

            if ($collection === null) {
                continue;
            }

            $states[$collection] = is_array($info->getOptions()['encryptedFields'] ?? null)
                ? CollectionState::Encrypted
                : CollectionState::NotEncrypted;
        }

        return $states;
    }
}
