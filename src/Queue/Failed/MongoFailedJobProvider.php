<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Queue\Failed;

use Carbon\Carbon;
use Exception;
use Illuminate\Queue\Failed\DatabaseFailedJobProvider;
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
     * @param mixed $id
     *
     * @return object
     */
    public function find($id)
    {
        $job = $this->getTable()->find($id);

        if (! $job) {
            return;
        }

        $job['id'] = (string) $job['_id'];

        return (object) $job;
    }

    /**
     * Delete a single failed job from storage.
     *
     * @param mixed $id
     *
     * @return bool
     */
    public function forget($id)
    {
        return $this->getTable()->where('_id', $id)->delete() > 0;
    }
}
