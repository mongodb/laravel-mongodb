<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Encryption\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use MongoDB\Driver\ClientEncryption;
use MongoDB\Driver\Exception\Exception as DriverException;
use MongoDB\Laravel\Connection;
use MongoDB\Laravel\Encryption\CollectionState;
use MongoDB\Laravel\Schema\Builder as SchemaBuilder;

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
        } catch (InvalidArgumentException | DriverException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Queryable Encryption configuration is valid.');

        try {
            $unencrypted = $this->reportCollections($connection, $encryptedFields);

            if (! $this->option('no-server')) {
                $this->line('Server version: ' . $connection->getServerVersion());
                $this->line(sprintf('Client-side encryption support: %s', class_exists(ClientEncryption::class) ? 'available' : 'unavailable'));
            }
        } catch (DriverException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $connectionName = $this->option('connection');
        $connectionOption = $connectionName ? ' --connection=' . $connectionName : '';

        foreach ($unencrypted as $collection) {
            $this->error(sprintf(
                'The collection "%s" exists without encryption: its mapped fields are stored in plaintext. Migrate the data, then create the collection again with "php artisan mongodb:encryption:create-collection %s --recreate%s".',
                $collection,
                $collection,
                $connectionOption,
            ));
        }

        return $unencrypted === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * The number of encrypted fields declared for each mapped collection.
     *
     * @param  array<string, mixed> $config
     *
     * @return array<array-key, int>
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
     * @param  array<array-key, int> $encryptedFields
     *
     * @return list<string> The mapped collections that exist without encryption.
     *
     * @throws DriverException
     */
    private function reportCollections(Connection $connection, array $encryptedFields): array
    {
        if ($encryptedFields === []) {
            $this->line('Mapped collections: none');

            return [];
        }

        $this->line('Mapped collections: ');

        $builder = $this->option('no-server') ? null : $connection->getSchemaBuilder();
        $unencrypted = [];

        foreach ($encryptedFields as $collection => $fields) {
            $state = $builder === null ? null : $this->collectionState($connection, $builder, (string) $collection);
            $label = $state === null ? '' : ', ' . $state->value;
            $line = sprintf('  - %s: %d encrypted field(s)%s', $collection, $fields, $label);

            if ($state === CollectionState::NotEncrypted) {
                $unencrypted[] = (string) $collection;
                $this->error($line);
            } else {
                $this->line($line);
            }
        }

        return $unencrypted;
    }

    private function collectionState(Connection $connection, SchemaBuilder $builder, string $collection): CollectionState
    {
        if ($builder->hasEncryptedCollection($collection)) {
            return CollectionState::Encrypted;
        }

        // hasEncryptedCollection() applies the connection prefix, hasCollection() does not.
        return $builder->hasCollection($connection->getTablePrefix() . $collection)
            ? CollectionState::NotEncrypted
            : CollectionState::Missing;
    }
}
