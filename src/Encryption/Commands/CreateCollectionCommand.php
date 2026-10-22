<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Encryption\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use InvalidArgumentException;
use MongoDB\Driver\Exception\Exception as DriverException;
use MongoDB\Laravel\Connection;
use MongoDB\Laravel\Encryption\CollectionState;
use MongoDB\Laravel\Schema\Builder;

use function array_diff;
use function array_filter;
use function array_intersect;
use function array_keys;
use function array_values;
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
        {--dry-run : List the mapped collections and report what would be created, without changing anything}
        {--connection= : The MongoDB connection to use}
        {--force : Force the operation to run when in production}';

    protected $description = 'Create Queryable Encryption collections from the configured encrypted fields map.';

    public function handle(): int
    {
        try {
            $connection = $this->connection();
            $config = $this->autoEncryptionConfig($connection);
            $fields = $this->mappedFields($connection, $config);
        } catch (InvalidArgumentException | DriverException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $mapped = $this->mappedCollectionNames($fields);
        $collection = $this->argument('collection');

        if ($collection !== null && ! in_array($collection, $mapped, true)) {
            $this->error(sprintf(
                'The collection "%s" is not declared in the "encryptedFieldsMap" driver option. Mapped collections: "%s".',
                $collection,
                implode('", "', $mapped),
            ));

            return self::INVALID;
        }

        try {
            $states = $this->collectionStates($connection, $fields);
        } catch (DriverException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->printCollectionStates($fields, $states);
        }

        $builder = $connection->getSchemaBuilder();
        $present = array_values(array_filter($mapped, static fn (string $name): bool => $states[$name]->state !== CollectionState::Missing));
        $missing = array_values(array_diff($mapped, $present));

        $targets = $this->targets($collection, $present, $missing);
        $dropped = $this->option('recreate') ? array_values(array_intersect($targets, $present)) : [];

        // A mapped collection that already exists is kept as it is, unless
        // --recreate drops it first, so it keeps its current fields.
        $kept = array_values(array_diff($present, $dropped));
        $leftBehind = array_filter(
            $kept,
            static fn (string $name): bool => $states[$name]->needsRecreate(),
        );

        // The collection to replace is not always the one selected on the
        // command line, so the warning names it and the command to run.
        foreach ($leftBehind as $name) {
            $this->warn($this->remediation($name, $states[$name]));
        }

        $status = self::SUCCESS;

        if ($targets === []) {
            $this->info($this->option('recreate')
                ? 'No mapped collection exists yet.'
                : 'Every mapped collection already exists.');
        } elseif ($dryRun) {
            foreach ($dropped as $name) {
                $this->line(sprintf('Would drop collection "%s".', $name));
            }

            foreach ($targets as $name) {
                $this->line(in_array($name, $kept, true)
                    ? sprintf('The collection "%s" already exists.', $name)
                    : sprintf('Would create encrypted collection "%s".', $name));
            }
        } else {
            if ($dropped !== [] && ! $this->confirmToProceed(sprintf('The collections "%s" will be dropped, removing their documents.', implode('", "', $dropped)))) {
                return self::FAILURE;
            }

            $status = $this->createCollections($builder, $targets, $dropped, $kept);
        }

        return $leftBehind === [] ? $status : self::FAILURE;
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
     * The encrypted fields declared for each collection of the encryptedFieldsMap,
     * keyed by the logical collection name.
     *
     * @param  array<string, mixed> $config
     *
     * @return array<string, list<array<string, mixed>>>
     *
     * @throws InvalidArgumentException
     */
    private function mappedFields(Connection $connection, array $config): array
    {
        $map = $config['encryptedFieldsMap'] ?? null;

        if (! is_array($map) || $map === []) {
            throw new InvalidArgumentException('No collection is declared in the "encryptedFieldsMap" driver option.');
        }

        $fields = [];
        foreach (array_keys($map) as $collection) {
            $fields[(string) $collection] = $connection->encryptedFieldsFor((string) $collection);
        }

        return $fields;
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
        // A dry run creates nothing, so it does not ask which collections to
        // create.
        if ($this->option('dry-run') || ! $this->input->isInteractive()) {
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
     * @param list<string> $kept    The targets that exist and are not dropped.
     */
    private function createCollections(Builder $builder, array $targets, array $dropped, array $kept): int
    {
        $failed = false;

        foreach ($targets as $collection) {
            try {
                if (in_array($collection, $dropped, true)) {
                    $builder->drop($collection);
                    $this->info(sprintf('Dropped collection "%s".', $collection));
                }

                $builder->createEncrypted($collection);

                $this->info(in_array($collection, $kept, true)
                    ? sprintf('The collection "%s" already exists.', $collection)
                    : sprintf('Created encrypted collection "%s".', $collection));
            } catch (InvalidArgumentException | DriverException $e) {
                $this->error(sprintf('%s: %s', $collection, $e->getMessage()));
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
