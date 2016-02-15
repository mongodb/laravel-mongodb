<?php

namespace Jenssegers\Mongodb\Queue\Failed;

use Carbon\Carbon;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Queue\Failed\FailedJobProviderInterface;

class DatabaseFailedJobProvider implements FailedJobProviderInterface {

    /**
     * The connection resolver implementation.
     *
     * @var \Illuminate\Database\ConnectionResolverInterface
     */
    protected $resolver;

    /**
     * The database connection name.
     *
     * @var string
     */
    protected $database;

    /**
     * The database table.
     *
     * @var string
     */
    protected $collection;

    /**
     * Create a new database failed job provider.
     *
     * @param  \Illuminate\Database\ConnectionResolverInterface  $resolver
     * @param  string  $database
     * @param  string  $collection
     * @return void
     */
    public function __construct(ConnectionResolverInterface $resolver, $database, $collection) {
        $this->collection = $collection;
        $this->resolver = $resolver;
        $this->database = $database;
    }

    /**
     * Log a failed job into storage.
     *
     * @param  string  $connection
     * @param  string  $queue
     * @param  string  $payload
     * @return void
     */
    public function log($connection, $queue, $payload) {
        $failed_at = Carbon::now();
        $this->getCollection()->insert(compact('connection', 'queue', 'payload', 'failed_at'));
    }

    /**
     * Get a list of all of the failed jobs.
     *
     * @return array
     */
    public function all() {
        $fails = $this->getCollection()->get();
        $jobs = [];
        foreach ($fails as $fail) {
            $fail = ['id' => (string) $fail['_id']] + $fail;
            unset($fail['_id']);
            $failed = new Carbon($fail['failed_at']['date'], $fail['failed_at']['timezone']);
            $fail['failed_at'] = (string) $failed;
            $jobs[] = $fail;
        }
        return $jobs;
    }

    /**
     * Get a single failed job.
     *
     * @param  mixed  $id
     * @return array
     */
    public function find($id) {
        $fail = $this->getCollection()->find($id);
        if ($fail) {
            $fail = ['id' => (string) $fail['_id']] + $fail;
            unset($fail['_id']);
            return (object) $fail;
        }
        return NULL;
    }

    /**
     * Delete a single failed job from storage.
     *
     * @param  mixed  $id
     * @return bool
     */
    public function forget($id) {
        return $this->getCollection()->delete($id) > 0;
    }

    /**
     * Flush all of the failed jobs from storage.
     *
     * @return void
     */
    public function flush() {
        $this->getCollection()->truncate();
    }

    /**
     * Get a new query builder instance for the table.
     *
     * @return \Jenssegers\Mongodb\Query\Builder
     */
    protected function getCollection() {
        return $this->resolver->connection($this->database)->collection($this->collection);
    }
}
