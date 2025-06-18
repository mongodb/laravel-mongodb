<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MongoDB\Laravel\Session;

use Illuminate\Session\DatabaseSessionHandler;
use MongoDB\BSON\Binary;
use MongoDB\BSON\Document;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use Override;

use function tap;
use function time;

/**
 * Session handler using the MongoDB driver extension.
 */
final class MongoDbSessionHandler extends DatabaseSessionHandler
{
    private Collection $collection;

    public function close(): bool
    {
        return true;
    }

    #[Override]
    public function gc($lifetime): int
    {
        $result = $this->getCollection()->deleteMany(['last_activity' => ['$lt' => $this->getUTCDateTime(-$lifetime)]]);

        return $result->getDeletedCount() ?? 0;
    }

    #[Override]
    public function destroy($sessionId): bool
    {
        $this->getCollection()->deleteOne(['_id' => (string) $sessionId]);

        return true;
    }

    #[Override]
    public function read($sessionId): string|false
    {
        $result = $this->getCollection()->findOne(
            ['_id' => (string) $sessionId, 'expires_at' => ['$gte' => $this->getUTCDateTime()]],
            [
                'projection' => ['_id' => false, 'payload' => true],
                'typeMap' => ['root' => 'bson'],
            ],
        );

        if ($result instanceof Document) {
            return (string) $result->payload;
        }

        return false;
    }

    #[Override]
    public function write($sessionId, $data): bool
    {
        $payload = $this->getDefaultPayload($data);

        $this->getCollection()->replaceOne(
            ['_id' => (string) $sessionId],
            $payload,
            ['upsert' => true],
        );

        return true;
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

    #[Override]
    protected function getDefaultPayload($data): array
    {
        $payload = [
            'payload' => new Binary($data),
            'last_activity' => $this->getUTCDateTime(),
            'expires_at' => $this->getUTCDateTime($this->minutes * 60),
        ];

        if (! $this->container) {
            return $payload;
        }

        return tap($payload, function (&$payload) {
            $this->addUserInformation($payload)
                ->addRequestInformation($payload);
        });
    }

    private function getCollection(): Collection
    {
        return $this->collection ??= $this->connection->getCollection($this->table);
    }

    private function getUTCDateTime(int $additionalSeconds = 0): UTCDateTime
    {
        return new UTCDateTime((time() + $additionalSeconds) * 1000);
    }
}
