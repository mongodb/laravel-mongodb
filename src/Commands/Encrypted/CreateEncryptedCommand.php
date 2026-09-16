<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Commands\Encrypted;

use Illuminate\Console\Command;
use InvalidArgumentException;

use function sprintf;

/**
 * Create an encrypted collection from the configured encrypted fields map.
 */
final class CreateEncryptedCommand extends Command
{
    use EncryptedCommand;

    protected $signature = 'mongodb:encrypted:create
        {collection : The collection to create}
        {--no-server : Only validate the configuration, do not contact the server}
        {--connection= : The MongoDB connection to use}';

    protected $description = 'Create a Queryable Encryption collection from the configured encrypted fields map.';

    public function handle(): int
    {
        $collection = $this->argument('collection');

        try {
            $connection = $this->connection();
            $connection->encryptedFieldsFor($collection);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('no-server')) {
            $this->info(sprintf('Configuration is valid. Encrypted collection "%s" would be created.', $collection));

            return self::SUCCESS;
        }

        $connection->getSchemaBuilder()->createEncrypted($collection);

        $this->info(sprintf('Created encrypted collection "%s".', $collection));

        return self::SUCCESS;
    }
}
