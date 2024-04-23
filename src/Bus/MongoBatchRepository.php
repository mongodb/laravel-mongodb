<?php

namespace MongoDB\Laravel\Bus;

use BadMethodCallException;
use Closure;
use DateTimeInterface;
use Illuminate\Bus\DatabaseBatchRepository;
use Illuminate\Bus\PendingBatch;
use Illuminate\Bus\PrunableBatchRepository;
use Illuminate\Bus\UpdatedBatchJobCounts;
use Illuminate\Support\Carbon;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Driver\ReadPreference;
use MongoDB\Laravel\Collection;
use Override;

use function is_string;

// Extending DatabaseBatchRepository is necessary so methods pruneUnfinished and pruneCancelled
// are called by PruneBatchesCommand
class MongoBatchRepository extends DatabaseBatchRepository implements PrunableBatchRepository
{
    public function __construct(
        private Collection $collection,
    ) {
    }

    #[Override]
    public function get($limit = 50, $before = null)
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
        );
    }

    #[Override]
    public function find(string $batchId)
    {
        $batchId = new ObjectId($batchId);

        return $this->collection->findOne(
            ['_id' => $batchId],
            ['readPreference' => ReadPreference::PRIMARY],
        );
    }

    #[Override]
    public function store(PendingBatch $batch)
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
    public function incrementTotalJobs(string $batchId, int $amount)
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
    public function decrementPendingJobs(string $batchId, string $jobId)
    {
        $batchId = new ObjectId($batchId);
        $values = $this->collection->findOneAndUpdate(
            ['_id' => $batchId],
            [
                '$dec' => ['pending_jobs' => 1],
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
    public function incrementFailedJobs(string $batchId, string $jobId)
    {
        // TODO: Implement incrementFailedJobs() method.
    }

    #[Override]
    public function markAsFinished(string $batchId)
    {
        $batchId = new ObjectId($batchId);
        $this->collection->updateOne(
            ['_id' => $batchId],
            ['$set' => ['finished_at' => new UTCDateTime(Carbon::now())]],
        );
    }

    #[Override]
    public function cancel(string $batchId)
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
    public function delete(string $batchId)
    {
        $batchId = new ObjectId($batchId);
        $this->collection->deleteOne(['_id' => $batchId]);
    }

    #[Override]
    public function transaction(Closure $callback)
    {
        // Transactions are not necessary
        return $callback();
    }

    /** Update an atomic value within the batch. */
    #[Override]
    public function rollBack()
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
}
