<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Encryption\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use InvalidArgumentException;
use MongoDB\Driver\Exception\Exception as DriverException;
use MongoDB\Laravel\Connection;
use MongoDB\Laravel\Schema\Builder;

use function array_diff;
use function array_filter;
use function array_intersect;
use function array_keys;
use function array_values;
use function count;
use function implode;
use function in_array;
use function is_array;
use function sprintf;

/**
 * Create the encrypted collections declared in the configured encrypted fields
 * map.
 */
final class CreateCollectionCommand extends Command
{
    use ConfirmableTrait;
    use InteractsWithEncryption;

    protected $signature = 'mongodb:encryption:create-collection
        {collection? : The collection to create, when omitted every missing mapped collection is created}
        {--recreate : Drop and recreate the mapped collections that already exist}
        {--no-server : Only validate the configuration, do not contact the server}
        {--connection= : The MongoDB connection to use}
        {--force : Force the operation to run when in production}';

    protected $description = 'Create Queryable Encryption collections from the configured encrypted fields map.';

    public function handle(): int
    {
        try {
            $connection = $this->connection();
            $config = $this->autoEncryptionConfig($connection);
            $mapped = $this->mappedCollections($connection, $config);
        } catch (InvalidArgumentException | DriverException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $collection = $this->argument('collection');

        if ($collection !== null && ! in_array($collection, $mapped, true)) {
            $this->error(sprintf(
                'The collection "%s" is not declared in the "encryptedFieldsMap" driver option. Mapped collections: "%s".',
                $collection,
                implode('", "', $mapped),
            ));

            return self::INVALID;
        }

        if ($this->option('no-server')) {
            $this->info($collection === null
                ? sprintf('Configuration is valid. %d encrypted collection(s) would be created.', count($mapped))
                : sprintf('Configuration is valid. Encrypted collection "%s" would be created.', $collection));

            return self::SUCCESS;
        }

        try {
            $builder = $connection->getSchemaBuilder();
            // hasEncryptedCollection() applies the connection prefix, hasCollection() does not.
            $prefix = $connection->getTablePrefix();
            $present = array_values(array_filter($mapped, static fn (string $name): bool => $builder->hasCollection($prefix . $name)));
            $encrypted = array_values(array_filter($present, $builder->hasEncryptedCollection(...)));
            $missing = array_values(array_diff($mapped, $present));
        } catch (DriverException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $targets = $this->targets($collection, $present, $missing);
        $dropped = $this->option('recreate') ? array_values(array_intersect($targets, $present)) : [];

        // A mapped collection that is not dropped keeps its plaintext fields.
        $unencrypted = array_values(array_diff($present, $encrypted, $dropped));

        // The collection to replace is not always the one selected on the
        // command line, so the warning names it and the command to run.
        $connectionName = $this->option('connection');
        $connectionOption = $connectionName ? ' --connection=' . $connectionName : '';

        foreach ($unencrypted as $name) {
            $this->warn(sprintf(
                'The collection "%s" exists without encryption: its mapped fields are stored in plaintext. Migrate the data, then create the collection again with "php artisan mongodb:encryption:create-collection %s --recreate%s".',
                $name,
                $name,
                $connectionOption,
            ));
        }

        $status = self::SUCCESS;

        if ($targets === []) {
            $this->info($this->option('recreate')
                ? 'No mapped collection exists yet.'
                : 'Every mapped collection already exists.');
        } else {
            if ($dropped !== [] && ! $this->confirmToProceed(sprintf('The collections "%s" will be dropped, removing their documents.', implode('", "', $dropped)))) {
                return self::FAILURE;
            }

            $status = $this->createCollections($builder, $targets, $dropped);
        }

        return $unencrypted === [] ? $status : self::FAILURE;
    }

    /**
     * @param  list<string> $present The mapped collections that exist, encrypted or not.
     * @param  list<string> $missing The mapped collections that do not exist.
     *
     * @return list<string>
     */
    private function targets(?string $collection, array $present, array $missing): array
    {
        if ($collection !== null) {
            return [$collection];
        }

        if ($this->option('recreate')) {
            return $present;
        }

        if ($missing === []) {
            return [];
        }

        return $this->selectCollections($missing);
    }

    /**
     * The collection names declared in the encryptedFieldsMap, each validated.
     *
     * @param  array<string, mixed> $config
     *
     * @return list<string>
     *
     * @throws InvalidArgumentException
     */
    private function mappedCollections(Connection $connection, array $config): array
    {
        $map = $config['encryptedFieldsMap'] ?? null;

        if (! is_array($map) || $map === []) {
            throw new InvalidArgumentException('No collection is declared in the "encryptedFieldsMap" driver option.');
        }

        $collections = [];
        foreach (array_keys($map) as $collection) {
            $connection->encryptedFieldsFor((string) $collection);
            $collections[] = (string) $collection;
        }

        return $collections;
    }

    /**
     * The missing collections to create, proposed for selection in an
     * interactive terminal, all selected by default.
     *
     * @param  non-empty-list<string> $missing
     *
     * @return list<string>
     */
    private function selectCollections(array $missing): array
    {
        if (! $this->input->isInteractive()) {
            return $missing;
        }

        // Symfony reads the default of a multiple choice as the indexes of the
        // selected answers, not as their values.
        return array_values((array) $this->choice(
            'Which encrypted collections do you want to create?',
            $missing,
            implode(',', array_keys($missing)),
            multiple: true,
        ));
    }

    /**
     * @param list<string> $targets
     * @param list<string> $dropped The targets that exist and are dropped first.
     */
    private function createCollections(Builder $builder, array $targets, array $dropped): int
    {
        $failed = false;

        foreach ($targets as $collection) {
            try {
                if (in_array($collection, $dropped, true)) {
                    $builder->drop($collection);
                    $this->info(sprintf('Dropped collection "%s".', $collection));
                }

                $builder->createEncrypted($collection);
                $this->info(sprintf('Created encrypted collection "%s".', $collection));
            } catch (InvalidArgumentException | DriverException $e) {
                $this->error(sprintf('%s: %s', $collection, $e->getMessage()));
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
