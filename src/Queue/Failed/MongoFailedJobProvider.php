<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Queue\Failed;

use Carbon\Carbon;
use DateTimeInterface;
use Exception;
use Illuminate\Queue\Failed\DatabaseFailedJobProvider;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

use function array_map;

class MongoFailedJobProvider extends DatabaseFailedJobProvider
{
    /**
     * Log a failed job into storage.
     *
     * @param string    $connection
     * @param string    $queue
     * @param string    $payload
     * @param Exception $exception
     *
     * @return void
     */
    public function log($connection, $queue, $payload, $exception)
    {
        $this->getTable()->insert([
            'connection' => $connection,
            'queue' => $queue,
            'payload' => $payload,
            'failed_at' => new UTCDateTime(Carbon::now()),
            'exception' => (string) $exception,
        ]);
    }

    /**
     * Get a list of all of the failed jobs.
     *
     * @return object[]
     */
    public function all()
    {
        $all = $this->getTable()->orderBy('_id', 'desc')->get()->all();

        $all = array_map(function ($job) {
            $job['id'] = (string) $job['_id'];

            return (object) $job;
        }, $all);

        return $all;
    }

    /**
     * Get a single failed job.
     *
     * @param string $id
     *
     * @return object|null
     */
    public function find($id)
    {
        $job = $this->getTable()->find($id);

        if (! $job) {
            return null;
        }

        $job['id'] = (string) $job['_id'];

        return (object) $job;
    }

    /**
     * Delete a single failed job from storage.
     *
     * @param string $id
     *
     * @return bool
     */
    public function forget($id)
    {
        return $this->getTable()->where('_id', $id)->delete() > 0;
    }

    /**
     * Get the IDs of all the failed jobs.
     *
     * @param  string|null $queue
     *
     * @return list<ObjectId>
     */
    public function ids($queue = null)
    {
        return $this->getTable()
            ->when($queue !== null, static fn ($query) => $query->where('queue', $queue))
            ->orderBy('_id', 'desc')
            ->pluck('_id')
            ->all();
    }

    /**
     * Prune all failed jobs older than the given date.
     *
     * @param  DateTimeInterface $before
     *
     * @return int
     */
    public function prune(DateTimeInterface $before)
    {
        return $this
            ->getTable()
            ->where('failed_at', '<', $before)
            ->delete();
    }
}
