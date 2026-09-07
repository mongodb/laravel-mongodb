<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Commands\Encrypted;

use InvalidArgumentException;

use function is_array;
use function sprintf;

/**
 * Create an encrypted collection from the configured encrypted fields map.
 */
class CreateEncryptedCommand extends EncryptedCommand
{
    protected $signature = 'mongodb:encrypted:create
        {collection : The collection to create}
        {--no-server : Only validate the configuration, do not contact the server}
        {--connection= : The MongoDB connection to use}';

    protected $description = 'Create a Queryable Encryption collection from the configured encrypted fields map.';

    public function handle(): int
    {
        $collection = $this->argument('collection');
        $connection = $this->connection();

        try {
            $config = $this->autoEncryptionConfig($connection);
            $connection->validateAutoEncryptionConfig($config);

            if (! is_array($config['encryptedFieldsMap'][$collection] ?? null)) {
                throw new InvalidArgumentException(sprintf('No "encryptedFieldsMap[%s]" entry is configured on this connection.', $collection));
            }
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
