<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Encryption\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use MongoDB\Driver\ClientEncryption;

use function array_keys;
use function class_exists;
use function sprintf;

/**
 * Diagnose the Queryable Encryption configuration and list mapped collections.
 */
final class StatusCommand extends Command
{
    use InteractsWithEncryption;

    protected $signature = 'mongodb:encryption:status
        {--no-server : Only validate the configuration, do not contact the server}
        {--connection= : The MongoDB connection to use}';

    protected $description = 'Validate the Queryable Encryption configuration and list encrypted collections.';

    public function handle(): int
    {
        try {
            $connection = $this->connection();
            $config = $this->autoEncryptionConfig($connection);
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
