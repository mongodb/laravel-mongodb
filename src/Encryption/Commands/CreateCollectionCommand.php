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
            $existing = array_values(array_filter($mapped, $builder->hasEncryptedCollection(...)));
            $missing = array_values(array_diff($mapped, $existing));
        } catch (DriverException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $targets = $this->targets($collection, $existing, $missing);

        if ($targets === []) {
            $this->info($this->option('recreate')
                ? 'No mapped encrypted collection exists yet.'
                : 'Every mapped encrypted collection already exists.');

            return self::SUCCESS;
        }

        if (! $this->confirmRecreate($targets, $existing)) {
            return self::FAILURE;
        }

        return $this->createCollections($builder, $targets);
    }

    /**
     * The collections to act on: the one named on the command line, the ones
     * that exist for --recreate, the missing ones otherwise.
     *
     * @param  list<string> $existing
     * @param  list<string> $missing
     *
     * @return list<string>
     */
    private function targets(?string $collection, array $existing, array $missing): array
    {
        if ($collection !== null) {
            return [$collection];
        }

        if ($this->option('recreate')) {
            return $existing;
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
     * Dropping collections removes their documents, so the operation is
     * confirmed in production.
     *
     * @param list<string> $targets
     * @param list<string> $existing
     */
    private function confirmRecreate(array $targets, array $existing): bool
    {
        $dropped = $this->option('recreate') ? array_values(array_intersect($targets, $existing)) : [];

        if ($dropped === []) {
            return true;
        }

        return $this->confirmToProceed(sprintf('The encrypted collections "%s" will be dropped, removing their documents.', implode('", "', $dropped)));
    }

    /** @param list<string> $targets */
    private function createCollections(Builder $builder, array $targets): int
    {
        $recreate = (bool) $this->option('recreate');
        $failed = false;

        foreach ($targets as $collection) {
            try {
                if ($recreate && $builder->hasEncryptedCollection($collection)) {
                    $builder->drop($collection);
                    $this->info(sprintf('Dropped encrypted collection "%s".', $collection));
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
