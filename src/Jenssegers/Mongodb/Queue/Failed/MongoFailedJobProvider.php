<?php

namespace Jenssegers\Mongodb\Queue\Failed;

use Carbon\Carbon;
use Illuminate\Queue\Failed\DatabaseFailedJobProvider;

class MongoFailedJobProvider extends DatabaseFailedJobProvider
{
    /**
     * Log a failed job into storage.
     *
     * @param  string $connection
     * @param  string $queue
     * @param  string $payload
     *
     * @return void
     */
    public function log($connection, $queue, $payload, $exception)
    {
        $failed_at = Carbon::now()->getTimestamp();

        $this->getTable()->insert(compact('connection', 'queue', 'payload', 'failed_at', 'exception'));
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
     * @param  mixed $id
     * @return object
     */
    public function find($id)
    {
        $job = $this->getTable()->find($id);

        $job['id'] = (string) $job['_id'];

        return (object) $job;
    }

    /**
     * Delete a single failed job from storage.
     *
     * @param  mixed $id
     * @return bool
     */
    public function forget($id)
    {
        return $this->getTable()->where('_id', $id)->delete() > 0;
    }
}
