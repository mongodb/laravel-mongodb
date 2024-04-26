<?php

namespace MongoDB\Laravel\Bus;

use BadMethodCallException;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchFactory;
use Illuminate\Bus\DatabaseBatchRepository;
use Illuminate\Bus\PendingBatch;
use Illuminate\Bus\PrunableBatchRepository;
use Illuminate\Bus\UpdatedBatchJobCounts;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Driver\ReadPreference;
use MongoDB\Laravel\Collection;
use Override;

use function assert;
use function date_default_timezone_get;
use function is_string;

// Extending DatabaseBatchRepository is necessary so methods pruneUnfinished and pruneCancelled
// are called by PruneBatchesCommand
class MongoBatchRepository extends DatabaseBatchRepository implements PrunableBatchRepository
{
    private Collection $collection;

    public function __construct(
        BatchFactory $factory,
        Connection $connection,
        string $collection,
    ) {
        assert($connection instanceof \MongoDB\Laravel\Connection);
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
            ['readPreference' => ReadPreference::PRIMARY],
        );

        return $this->factory->make(
            $this,
            $batch['id'],
            $batch['name'],
            $batch['total_jobs'],
            $batch['pending_jobs'],
            $batch['failed_jobs'],
            $batch['failed_job_ids'],
            $batch['options'],
            CarbonImmutable::createFromTimestamp($batch['created_at']->getTimestamp(), date_default_timezone_get()),
            $batch['cancelled_at'] ? CarbonImmutable::createFromTimestamp($batch['cancelled_at']->getTimestamp(), date_default_timezone_get()) : null,
            $batch['finished_at'] ? CarbonImmutable::createFromTimestamp($batch['finished_at']->getTimestamp(), date_default_timezone_get()) : null,
        );
    }

    #[Override]
    public function store(PendingBatch $batch): Batch
    {
        $this->collection->insertOne([
            'name' => $batch->name,
            'total_jobs' => 0,
            'pending_jobs' => 0,
            'failed_jobs' => 0,
            'failed_job_ids' => '[]',
            'options' => $this->serialize($batch->options),
            'created_at' => new UTCDateTime(Carbon::now()),
            'cancelled_at' => null,
            'finished_at' => null,
        ]);
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
                '$dec' => ['pending_jobs' => 1],
                '$pull' => ['failed_job_ids' => $jobId],
            ],
            [
                'projection' => ['pending_jobs' => 1, 'failed_jobs' => 1],
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
                '$inc' => ['pending_jobs' => 1],
                '$push' => ['failed_job_ids' => $jobId],
            ],
            [
                'projection' => ['pending_jobs' => 1, 'failed_jobs' => 1],
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
            ['$set' => ['finished_at' => new UTCDateTime(Carbon::now())]],
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
                    'cancelled_at' => new UTCDateTime(Carbon::now()),
                    'finished_at' => new UTCDateTime(Carbon::now()),
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
        // Transactions are not necessary
        return $callback();
    }

    /**
     * Rollback the last database transaction for the connection.
     *
     * Not implemented.
     */
    #[Override]
    public function rollBack(): void
    {
        throw new BadMethodCallException('Not implemented');
    }

    /** Mark the batch that has the given ID as finished. */
    #[Override]
    public function prune(DateTimeInterface $before): int
    {
        $result = $this->collection->deleteMany(
            ['finished_at' => ['$ne' => null, '$lt' => new UTCDateTime($before)]],
        );

        return $result->getDeletedCount();
    }

    /** Prune all the unfinished entries older than the given date. */
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

    #[Override]
    protected function toBatch($batch): Batch
    {
        return $this->factory->make(
            $this,
            $batch->id,
            $batch->name,
            $batch->total_jobs,
            $batch->pending_jobs,
            $batch->failed_jobs,
            $batch->failed_job_ids,
            $batch->options,
            CarbonImmutable::createFromTimestamp($batch->created_at->getTimestamp(), date_default_timezone_get()),
            $batch->cancelled_at ? CarbonImmutable::createFromTimestamp($batch->cancelled_at->getTimestamp(), date_default_timezone_get()) : null,
            $batch->finished_at ? CarbonImmutable::createFromTimestamp($batch->finished_at->getTimestamp(), date_default_timezone_get()) : null,
        );
    }
}
