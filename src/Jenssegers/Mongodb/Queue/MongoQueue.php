<?php namespace Jenssegers\Mongodb\Queue;

use Carbon\Carbon;
use Illuminate\Queue\DatabaseQueue;

class MongoQueue extends DatabaseQueue
{
    /**
     * Get the next available job for the queue.
     *
     * @param  string|null  $queue
     * @return \StdClass|null
     */
    protected function getNextAvailableJob($queue)
    {
        $job = $this->database->table($this->table)
                    ->lockForUpdate()
                    ->where('queue', $this->getQueue($queue))
                    ->where('reserved', 0)
                    ->where('available_at', '<=', $this->getTime())
                    ->orderBy('id', 'asc')
                    ->first();

        if ($job) {
            $job = (object) $job;
            $job->id = $job->_id;
        }

        return $job ?: null;
    }

    /**
     * Release the jobs that have been reserved for too long.
     *
     * @param  string  $queue
     * @return void
     */
    protected function releaseJobsThatHaveBeenReservedTooLong($queue)
    {
        $expired = Carbon::now()->subSeconds($this->expire)->getTimestamp();

        $reserved = $this->database->collection($this->table)
                    ->where('queue', $this->getQueue($queue))
                    ->where('reserved', 1)
                    ->where('reserved_at', '<=', $expired)->get();

        foreach ($reserved as $job) {
            $attempts = $job['attempts'] + 1;
            $this->releaseJob($job['_id'], $attempts);
        }
    }

    /**
     * Release the given job ID from reservation.
     *
     * @param  string $id
     *
     * @return void
     */
    protected function releaseJob($id, $attempts)
    {
        $this->database->table($this->table)->where('_id', $id)->update([
            'reserved'    => 0,
            'reserved_at' => null,
            'attempts'    => $attempts,
        ]);
    }

    /**
     * Mark the given job ID as reserved.
     *
     * @param  string  $id
     * @return void
     */
    protected function markJobAsReserved($id)
    {
        $this->database->collection($this->table)->where('_id', $id)->update([
            'reserved' => 1, 'reserved_at' => $this->getTime(),
        ]);
    }

    /**
     * Delete a reserved job from the queue.
     *
     * @param  string  $queue
     * @param  string  $id
     * @return void
     */
    public function deleteReserved($queue, $id)
    {
        $this->database->table($this->table)->where('_id', $id)->delete();
    }
}
