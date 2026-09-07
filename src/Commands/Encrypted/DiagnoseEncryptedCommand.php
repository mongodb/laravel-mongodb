<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Commands\Encrypted;

use InvalidArgumentException;
use MongoDB\Driver\ClientEncryption;

use function array_keys;
use function class_exists;
use function sprintf;

/**
 * Diagnose the Queryable Encryption configuration and list mapped collections.
 */
class DiagnoseEncryptedCommand extends EncryptedCommand
{
    protected $signature = 'mongodb:encrypted:diagnose
        {--no-server : Only validate the configuration, do not contact the server}
        {--connection= : The MongoDB connection to use}';

    protected $description = 'Validate the Queryable Encryption configuration and list encrypted collections.';

    public function handle(): int
    {
        $connection = $this->connection();

        try {
            $config = $this->autoEncryptionConfig($connection);
            $connection->validateAutoEncryptionConfig($config);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Queryable Encryption configuration is valid.');

        $mappedCollections = array_keys($config['encryptedFieldsMap'] ?? []);
        $this->line('Mapped collections: ');
        foreach ($mappedCollections as $collection) {
            $this->line('  - ' . $collection);
        }

        if (! $this->option('no-server')) {
            $this->line('Server version: ' . $connection->getServerVersion());
            $this->line(sprintf('Client-side encryption support: %s', class_exists(ClientEncryption::class) ? 'available' : 'unavailable'));
        }

        return self::SUCCESS;
    }
}
