<?php

namespace MongoDB\Laravel\Cache;

use Illuminate\Cache\Lock;
use Illuminate\Support\Carbon;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Laravel\Collection;
use MongoDB\Operation\FindOneAndUpdate;
use Override;

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
        $result = $this->collection->updateOne(
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

        return $result->getModifiedCount() > 0 || $result->getUpsertedCount() > 0;
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
