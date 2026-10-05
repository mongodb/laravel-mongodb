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
use function count;
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
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Queryable Encryption configuration is valid.');

        try {
            $this->reportCollections($connection, $encryptedFields);

            if (! $this->option('no-server')) {
                $this->line('Server version: ' . $connection->getServerVersion());
                $this->line(sprintf('Client-side encryption support: %s', class_exists(ClientEncryption::class) ? 'available' : 'unavailable'));
            }
        } catch (DriverException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * The number of encrypted fields declared for each mapped collection.
     *
     * @param  array<string, mixed> $config
     *
     * @return array<string, int>
     *
     * @throws InvalidArgumentException
     */
    private function encryptedFields(Connection $connection, array $config): array
    {
        $counts = [];

        foreach (array_keys($config['encryptedFieldsMap'] ?? []) as $collection) {
            $counts[(string) $collection] = count($connection->encryptedFieldsFor((string) $collection));
        }

        return $counts;
    }

    /**
     * @param array<string, int> $encryptedFields
     *
     * @throws DriverException
     */
    private function reportCollections(Connection $connection, array $encryptedFields): void
    {
        if ($encryptedFields === []) {
            $this->line('Mapped collections: none');

            return;
        }

        $this->line('Mapped collections: ');

        $builder = $this->option('no-server') ? null : $connection->getSchemaBuilder();

        foreach ($encryptedFields as $collection => $fields) {
            $this->line(sprintf(
                '  - %s: %d encrypted field(s)%s',
                $collection,
                $fields,
                $builder === null ? '' : ($builder->hasEncryptedCollection($collection) ? ', exists' : ', missing'),
            ));
        }
    }
}
