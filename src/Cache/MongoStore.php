<?php

namespace MongoDB\Laravel\Cache;

use Illuminate\Cache\RetrievesMultipleKeys;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Carbon;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Laravel\Collection;
use MongoDB\Laravel\Connection;
use MongoDB\Operation\FindOneAndUpdate;
use Override;

use function is_float;
use function is_int;
use function is_string;
use function serialize;
use function str_contains;
use function unserialize;

final class MongoStore implements LockProvider, Store
{
    // Provides "many" and "putMany" in a non-optimized way
    use RetrievesMultipleKeys;

    private const TEN_YEARS_IN_SECONDS = 315360000;

    private Collection $collection;

    /**
     * @param Connection      $connection                  The MongoDB connection to use for the cache
     * @param string          $collectionName              Name of the collection where cache items are stored
     * @param string          $prefix                      Prefix for the name of cache items
     * @param Connection|null $lockConnection              The MongoDB connection to use for the lock, if different from the cache connection
     * @param string          $lockCollectionName          Name of the collection where locks are stored
     * @param array{int, int} $lockLottery                 Probability [chance, total] of pruning expired cache items. Set to [0, 0] to disable
     * @param int             $defaultLockTimeoutInSeconds Time-to-live of the locks in seconds
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly string $collectionName = 'cache',
        private readonly string $prefix = '',
        private readonly ?Connection $lockConnection = null,
        private readonly string $lockCollectionName = 'cache_locks',
        private readonly array $lockLottery = [2, 100],
        private readonly int $defaultLockTimeoutInSeconds = 86400,
    ) {
        $this->collection = $this->connection->getCollection($this->collectionName);
    }

    /**
     * Get a lock instance.
     *
     * @param string      $name
     * @param int         $seconds
     * @param string|null $owner
     */
    #[Override]
    public function lock($name, $seconds = 0, $owner = null): MongoLock
    {
        return new MongoLock(
            ($this->lockConnection ?? $this->connection)->getCollection($this->lockCollectionName),
            $this->prefix . $name,
            $seconds ?: $this->defaultLockTimeoutInSeconds,
            $owner,
            $this->lockLottery,
        );
    }

    /**
     * Restore a lock instance using the owner identifier.
     */
    #[Override]
    public function restoreLock($name, $owner): MongoLock
    {
        return $this->lock($name, 0, $owner);
    }

    /**
     * Store an item in the cache for a given number of seconds.
     *
     * @param string $key
     * @param mixed  $value
     * @param int    $seconds
     */
    #[Override]
    public function put($key, $value, $seconds): bool
    {
        $result = $this->collection->updateOne(
            [
                '_id' => $this->prefix . $key,
            ],
            [
                '$set' => [
                    'value' => $this->serialize($value),
                    'expires_at' => $this->getUTCDateTime($seconds),
                ],
            ],
            [
                'upsert' => true,

            ],
        );

        return $result->getUpsertedCount() > 0 || $result->getModifiedCount() > 0;
    }

    /**
     * Store an item in the cache if the key doesn't exist.
     *
     * @param string $key
     * @param mixed  $value
     * @param int    $seconds
     */
    public function add($key, $value, $seconds): bool
    {
        $isExpired = ['$lte' => ['$expires_at', $this->getUTCDateTime()]];

        $result = $this->collection->updateOne(
            [
                '_id' => $this->prefix . $key,
            ],
            [
                [
                    '$set' => [
                        'value' => [
                            '$cond' => [
                                'if' => $isExpired,
                                'then' => $this->serialize($value),
                                'else' => '$value',
                            ],
                        ],
                        'expires_at' => [
                            '$cond' => [
                                'if' => $isExpired,
                                'then' => $this->getUTCDateTime($seconds),
                                'else' => '$expires_at',
                            ],
                        ],
                    ],
                ],
            ],
            ['upsert' => true],
        );

        return $result->getUpsertedCount() > 0 || $result->getModifiedCount() > 0;
    }

    /**
     * Retrieve an item from the cache by key.
     *
     * @param string $key
     */
    #[Override]
    public function get($key): mixed
    {
        $result = $this->collection->findOne(
            ['_id' => $this->prefix . $key],
            ['projection' => ['value' => 1, 'expires_at' => 1]],
        );

        if (! $result) {
            return null;
        }

        if ($result['expires_at'] <= $this->getUTCDateTime()) {
            $this->forgetIfExpired($key);

            return null;
        }

        return $this->unserialize($result['value']);
    }

    /**
     * Increment the value of an item in the cache.
     *
     * @param string    $key
     * @param int|float $value
     */
    #[Override]
    public function increment($key, $value = 1): int|float|false
    {
        $result = $this->collection->findOneAndUpdate(
            [
                '_id' => $this->prefix . $key,
            ],
            [
                '$inc' => ['value' => $value],
            ],
            [
                'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER,
            ],
        );

        if (! $result) {
            return false;
        }

        if ($result['expires_at'] <= $this->getUTCDateTime()) {
            $this->forgetIfExpired($key);

            return false;
        }

        return $result['value'];
    }

    /**
     * Decrement the value of an item in the cache.
     *
     * @param string    $key
     * @param int|float $value
     */
    #[Override]
    public function decrement($key, $value = 1): int|float|false
    {
        return $this->increment($key, -1 * $value);
    }

    /**
     * Store an item in the cache indefinitely.
     *
     * @param string $key
     * @param mixed  $value
     */
    #[Override]
    public function forever($key, $value): bool
    {
        return $this->put($key, $value, self::TEN_YEARS_IN_SECONDS);
    }

    /**
     * Remove an item from the cache.
     *
     * @param string $key
     */
    #[Override]
    public function forget($key): bool
    {
        $result = $this->collection->deleteOne([
            '_id' => $this->prefix . $key,
        ]);

        return $result->getDeletedCount() > 0;
    }

    /**
     * Remove an item from the cache if it is expired.
     *
     * @param string $key
     */
    public function forgetIfExpired($key): bool
    {
        $result = $this->collection->deleteOne([
            '_id' => $this->prefix . $key,
            'expires_at' => ['$lte' => $this->getUTCDateTime()],
        ]);

        return $result->getDeletedCount() > 0;
    }

    public function flush(): bool
    {
        $this->collection->deleteMany([]);

        return true;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /** Creates a TTL index that automatically deletes expired objects. */
    public function createTTLIndex(): void
    {
        $this->collection->createIndex(
            // UTCDateTime field that holds the expiration date
            ['expires_at' => 1],
            // Delay to remove items after expiration
            ['expireAfterSeconds' => 0],
        );
    }

    private function serialize($value): string|int|float
    {
        // Don't serialize numbers, so they can be incremented
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        return serialize($value);
    }

    private function unserialize($value): mixed
    {
        if (! is_string($value) || ! str_contains($value, ';')) {
            return $value;
        }

        return unserialize($value);
    }

    private function getUTCDateTime(int $additionalSeconds = 0): UTCDateTime
    {
        return new UTCDateTime(Carbon::now()->addSeconds($additionalSeconds));
    }
}
