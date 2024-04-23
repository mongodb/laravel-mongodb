<?php

namespace MongoDB\Laravel\Cache;

use Illuminate\Cache\Lock;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Laravel\Collection;
use MongoDB\Operation\FindOneAndUpdate;
use Override;

use function is_numeric;
use function random_int;

final class MongoLock extends Lock
{
    /**
     * Create a new lock instance.
     *
     * @param Collection      $collection The MongoDB collection
     * @param string          $name       Name of the lock
     * @param int             $seconds    Time-to-live of the lock in seconds
     * @param string|null     $owner      A unique string that identifies the owner. Random if not set
     * @param array{int, int} $lottery    Probability [chance, total] of pruning expired cache items. Set to [0, 0] to disable
     */
    public function __construct(
        private readonly Collection $collection,
        string $name,
        int $seconds,
        ?string $owner = null,
        private readonly array $lottery = [2, 100],
    ) {
        if (! is_numeric($this->lottery[0] ?? null) || ! is_numeric($this->lottery[1] ?? null) || $this->lottery[0] > $this->lottery[1]) {
            throw new InvalidArgumentException('Lock lottery must be a couple of integers [$chance, $total] where $chance <= $total. Example [2, 100]');
        }

        parent::__construct($name, $seconds, $owner);
    }

    /**
     * Attempt to acquire the lock.
     */
    public function acquire(): bool
    {
        // The lock can be acquired if: it doesn't exist, it has expired,
        // or it is already owned by the same lock instance.
        $isExpiredOrAlreadyOwned = [
            '$or' => [
                ['$lte' => ['$expires_at', $this->getUTCDateTime()]],
                ['$eq' => ['$owner', $this->owner]],
            ],
        ];
        $result = $this->collection->findOneAndUpdate(
            ['_id' => $this->name],
            [
                [
                    '$set' => [
                        'owner' => [
                            '$cond' => [
                                'if' => $isExpiredOrAlreadyOwned,
                                'then' => $this->owner,
                                'else' => '$owner',
                            ],
                        ],
                        'expires_at' => [
                            '$cond' => [
                                'if' => $isExpiredOrAlreadyOwned,
                                'then' => $this->getUTCDateTime($this->seconds),
                                'else' => '$expires_at',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'upsert' => true,
                'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER,
                'projection' => ['owner' => 1],
            ],
        );

        if ($this->lottery[0] <= 0 && random_int(1, $this->lottery[1]) <= $this->lottery[0]) {
            $this->collection->deleteMany(['expires_at' => ['$lte' => $this->getUTCDateTime()]]);
        }

        // Compare the owner to check if the lock is owned. Acquiring the same lock
        // with the same owner at the same instant would lead to not update the document
        return $result['owner'] === $this->owner;
    }

    /**
     * Release the lock.
     */
    #[Override]
    public function release(): bool
    {
        $result = $this->collection
            ->deleteOne([
                '_id' => $this->name,
                'owner' => $this->owner,
            ]);

        return $result->getDeletedCount() > 0;
    }

    /**
     * Releases this lock in disregard of ownership.
     */
    #[Override]
    public function forceRelease(): void
    {
        $this->collection->deleteOne([
            '_id' => $this->name,
        ]);
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

    /**
     * Returns the owner value written into the driver for this lock.
     */
    #[Override]
    protected function getCurrentOwner(): ?string
    {
        return $this->collection->findOne(
            [
                '_id' => $this->name,
                'expires_at' => ['$gte' => $this->getUTCDateTime()],
            ],
            ['projection' => ['owner' => 1]],
        )['owner'] ?? null;
    }

    private function getUTCDateTime(int $additionalSeconds = 0): UTCDateTime
    {
        return new UTCDateTime(Carbon::now()->addSeconds($additionalSeconds));
    }
}
