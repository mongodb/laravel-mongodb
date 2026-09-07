<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Commands\Encrypted;

use Illuminate\Console\Command;
use InvalidArgumentException;
use MongoDB\Laravel\Connection;

use function is_array;
use function is_string;
use function sprintf;

/**
 * Base command shared by the Queryable Encryption CLI tools.
 */
abstract class EncryptedCommand extends Command
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
     * Get the validated driver_options.autoEncryption configuration.
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
}
