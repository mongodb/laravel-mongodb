<?php

namespace MongoDB\Laravel\Bus;

use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchFactory;
use Illuminate\Bus\DatabaseBatchRepository;
use Illuminate\Bus\PendingBatch;
use Illuminate\Bus\PrunableBatchRepository;
use Illuminate\Bus\UpdatedBatchJobCounts;
use Illuminate\Support\Carbon;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Driver\ReadPreference;
use MongoDB\Laravel\Connection;
use MongoDB\Operation\FindOneAndUpdate;
use Override;

use function is_string;
use function serialize;
use function unserialize;

// Extending DatabaseBatchRepository is necessary so methods pruneUnfinished and pruneCancelled
// are called by PruneBatchesCommand
class MongoBatchRepository extends DatabaseBatchRepository implements PrunableBatchRepository
{
    private readonly Collection $collection;

    public function __construct(
        BatchFactory $factory,
        Connection $connection,
        string $collection,
    ) {
        $this->collection = $connection->getCollection($collection);

        parent::__construct($factory, $connection, $collection);
    }

    #[Override]
    public function get($limit = 50, $before = null): array
    {
        if (is_string($before)) {
            $before = new ObjectId($before);
        }

        return $this->collection->find(
            $before ? ['_id' => ['$lt' => $before]] : [],
            [
                'limit' => $limit,
                'sort' => ['_id' => -1],
                'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
            ],
        )->toArray();
    }

    #[Override]
    public function find(string $batchId): ?Batch
    {
        $batchId = new ObjectId($batchId);

        $batch = $this->collection->findOne(
            ['_id' => $batchId],
            [
                // If the select query is executed faster than the database replication takes place,
                // then no batch is found. In that case an exception is thrown because jobs are added
                // to a null batch.
                'readPreference' => new ReadPreference(ReadPreference::PRIMARY),
                'typeMap' => ['root' => 'array', 'array' => 'array', 'document' => 'array'],
            ],
        );

        return $batch ? $this->toBatch($batch) : null;
    }

    #[Override]
    public function store(PendingBatch $batch): Batch
    {
        $batch = [
            'name' => $batch->name,
            'total_jobs' => 0,
            'pending_jobs' => 0,
            'failed_jobs' => 0,
            'failed_job_ids' => [],
            // Serialization is required for Closures
            'options' => serialize($batch->options),
            'created_at' => $this->getUTCDateTime(),
            'cancelled_at' => null,
            'finished_at' => null,
        ];
        $result = $this->collection->insertOne($batch);

        return $this->toBatch(['_id' => $result->getInsertedId()] + $batch);
    }

    #[Override]
    public function incrementTotalJobs(string $batchId, int $amount): void
    {
        $batchId = new ObjectId($batchId);
        $this->collection->updateOne(
            ['_id' => $batchId],
            [
                '$inc' => [
                    'total_jobs' => $amount,
                    'pending_jobs' => $amount,
                ],
                '$set' => [
                    'finished_at' => null,
                ],
            ],
        );
    }

    #[Override]
    public function decrementPendingJobs(string $batchId, string $jobId): UpdatedBatchJobCounts
    {
        $batchId = new ObjectId($batchId);
        $values = $this->collection->findOneAndUpdate(
            ['_id' => $batchId],
            [
                '$inc' => ['pending_jobs' => -1],
                '$pull' => ['failed_job_ids' => $jobId],
            ],
            [
                'projection' => ['pending_jobs' => 1, 'failed_jobs' => 1],
                'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER,
            ],
        );

        return new UpdatedBatchJobCounts(
            $values['pending_jobs'],
            $values['failed_jobs'],
        );
    }

    #[Override]
    public function incrementFailedJobs(string $batchId, string $jobId): UpdatedBatchJobCounts
    {
        $batchId = new ObjectId($batchId);
        $values = $this->collection->findOneAndUpdate(
            ['_id' => $batchId],
            [
                '$inc' => ['failed_jobs' => 1],
                '$push' => ['failed_job_ids' => $jobId],
            ],
            [
                'projection' => ['pending_jobs' => 1, 'failed_jobs' => 1],
                'returnDocument' => FindOneAndUpdate::RETURN_DOCUMENT_AFTER,
            ],
        );

        return new UpdatedBatchJobCounts(
            $values['pending_jobs'],
            $values['failed_jobs'],
        );
    }

    #[Override]
    public function markAsFinished(string $batchId): void
    {
        $batchId = new ObjectId($batchId);
        $this->collection->updateOne(
            ['_id' => $batchId],
            ['$set' => ['finished_at' => $this->getUTCDateTime()]],
        );
    }

    #[Override]
    public function cancel(string $batchId): void
    {
        $batchId = new ObjectId($batchId);
        $this->collection->updateOne(
            ['_id' => $batchId],
            [
                '$set' => [
                    'cancelled_at' => $this->getUTCDateTime(),
                    'finished_at' => $this->getUTCDateTime(),
                ],
            ],
        );
    }

    #[Override]
    public function delete(string $batchId): void
    {
        $batchId = new ObjectId($batchId);
        $this->collection->deleteOne(['_id' => $batchId]);
    }

    /** Execute the given Closure within a storage specific transaction. */
    #[Override]
    public function transaction(Closure $callback): mixed
    {
        return $this->connection->transaction($callback);
    }

    /** Rollback the last database transaction for the connection. */
    #[Override]
    public function rollBack(): void
    {
        $this->connection->rollBack();
    }

    /** Prune the entries older than the given date. */
    #[Override]
    public function prune(DateTimeInterface $before): int
    {
        $result = $this->collection->deleteMany(
            ['finished_at' => ['$ne' => null, '$lt' => new UTCDateTime($before)]],
        );

        return $result->getDeletedCount();
    }

    /** Prune all the unfinished entries older than the given date. */
    #[Override]
    public function pruneUnfinished(DateTimeInterface $before): int
    {
        $result = $this->collection->deleteMany(
            [
                'finished_at' => null,
                'created_at' => ['$lt' => new UTCDateTime($before)],
            ],
        );

        return $result->getDeletedCount();
    }

    /** Prune all the cancelled entries older than the given date. */
    #[Override]
    public function pruneCancelled(DateTimeInterface $before): int
    {
        $result = $this->collection->deleteMany(
            [
                'cancelled_at' => ['$ne' => null],
                'created_at' => ['$lt' => new UTCDateTime($before)],
            ],
        );

        return $result->getDeletedCount();
    }

    /** @param array $batch */
    #[Override]
    protected function toBatch($batch): Batch
    {
        return $this->factory->make(
            $this,
            $batch['_id'],
            $batch['name'],
            $batch['total_jobs'],
            $batch['pending_jobs'],
            $batch['failed_jobs'],
            $batch['failed_job_ids'],
            unserialize($batch['options']),
            $this->toCarbon($batch['created_at']),
            $this->toCarbon($batch['cancelled_at']),
            $this->toCarbon($batch['finished_at']),
        );
    }

    private function getUTCDateTime(): UTCDateTime
    {
        // Using Carbon so the current time can be modified for tests
        return new UTCDateTime(Carbon::now());
    }

    /** @return ($date is null ? null : CarbonImmutable) */
    private function toCarbon(?UTCDateTime $date): ?CarbonImmutable
    {
        if ($date === null) {
            return null;
        }

        return CarbonImmutable::createFromTimestampMsUTC((string) $date);
    }
}
