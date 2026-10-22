<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Encryption\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use MongoDB\Driver\ClientEncryption;
use MongoDB\Driver\Exception\Exception as DriverException;
use MongoDB\Laravel\Connection;

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
            $encryptedFields = $this->encryptedFields($connection, $config);
        } catch (InvalidArgumentException | DriverException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Queryable Encryption configuration is valid.');

        try {
            $states = $this->option('no-server') ? null : $this->collectionStates($connection, $encryptedFields);
            $problems = $this->printCollectionStates($encryptedFields, $states);

            if ($states !== null) {
                $this->line('Server version: ' . $connection->getServerVersion());
                $this->line(sprintf('Client-side encryption support: %s', class_exists(ClientEncryption::class) ? 'available' : 'unavailable'));
            }
        } catch (DriverException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($problems as [$collection, $status]) {
            $this->error($this->remediation($collection, $status));
        }

        return $problems === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * The encrypted fields declared for each mapped collection.
     *
     * @param  array<string, mixed> $config
     *
     * @return array<string, list<array<string, mixed>>>
     *
     * @throws InvalidArgumentException
     */
    private function encryptedFields(Connection $connection, array $config): array
    {
        $fields = [];

        foreach (array_keys($config['encryptedFieldsMap'] ?? []) as $collection) {
            $fields[(string) $collection] = $connection->encryptedFieldsFor((string) $collection);
        }

        return $fields;
    }
}
