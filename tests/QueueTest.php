<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Laravel\Queue\Failed\MongoFailedJobProvider;
use MongoDB\Laravel\Queue\MongoJob;
use MongoDB\Laravel\Queue\MongoQueue;

use function app;
use function json_encode;

class QueueTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // Always start with a clean slate
        Queue::getDatabase()->table(Config::get('queue.connections.database.table'))->truncate();
        Queue::getDatabase()->table(Config::get('queue.failed.table'))->truncate();
    }

    public function testQueueJobLifeCycle(): void
    {
        $uuid = Str::uuid();
        Str::createUuidsUsing(fn () => $uuid);

        $id = Queue::push('test', ['action' => 'QueueJobLifeCycle'], 'test');
        $this->assertNotNull($id);

        // Get and reserve the test job (next available)
        $job = Queue::pop('test');
        $this->assertInstanceOf(MongoJob::class, $job);
        $this->assertEquals(1, $job->isReserved());
        $this->assertEquals(json_encode([
            'uuid' => $uuid,
            'displayName' => 'test',
            'job' => 'test',
            'maxTries' => null,
            'maxExceptions' => null,
            'failOnTimeout' => false,
            'backoff' => null,
            'timeout' => null,
            'data' => ['action' => 'QueueJobLifeCycle'],
        ]), $job->getRawBody());

        // Remove reserved job
        $job->delete();
        $this->assertEquals(0, Queue::getDatabase()->table(Config::get('queue.connections.database.table'))->count());

        Str::createUuidsNormally();
    }

    public function testQueueJobExpired(): void
    {
        $id = Queue::push('test', ['action' => 'QueueJobExpired'], 'test');
        $this->assertNotNull($id);

        // Expire the test job
        $expiry = Carbon::now()->subSeconds(Config::get('queue.connections.database.expire'))->getTimestamp();
        Queue::getDatabase()
            ->table(Config::get('queue.connections.database.table'))
            ->where('_id', $id)
            ->update(['reserved' => 1, 'reserved_at' => $expiry]);

        // Expect an attempted older job in the queue
        $job = Queue::pop('test');
        $this->assertEquals(1, $job->attempts());
        $this->assertGreaterThan($expiry, $job->reservedAt());

        $job->delete();
        $this->assertEquals(0, Queue::getDatabase()->table(Config::get('queue.connections.database.table'))->count());
    }

    public function testFailQueueJob(): void
    {
        $provider = app('queue.failer');

        $this->assertInstanceOf(MongoFailedJobProvider::class, $provider);
    }

    public function testFindFailJobNull(): void
    {
        Config::set('queue.failed.database', 'mongodb');
        $provider = app('queue.failer');

        $this->assertNull($provider->find(1));
    }

    public function testIncrementAttempts(): void
    {
        $jobId = Queue::push('test1', ['action' => 'QueueJobExpired'], 'test');
        $this->assertNotNull($jobId);
        $jobId = Queue::push('test2', ['action' => 'QueueJobExpired'], 'test');
        $this->assertNotNull($jobId);

        $job = Queue::pop('test');
        $this->assertEquals(1, $job->attempts());
        $job->delete();

        $othersJobs = Queue::getDatabase()
            ->table(Config::get('queue.connections.database.table'))
            ->get();

        $this->assertCount(1, $othersJobs);
        $this->assertEquals(0, $othersJobs[0]['attempts']);
    }

    public function testJobRelease(): void
    {
        $queue = 'test';
        $jobId = Queue::push($queue, ['action' => 'QueueJobRelease'], 'test');
        $this->assertNotNull($jobId);

        $job = Queue::pop($queue);
        $job->release();

        $jobs = Queue::getDatabase()
            ->table(Config::get('queue.connections.database.table'))
            ->get();

        $this->assertCount(1, $jobs);
        $this->assertEquals(1, $jobs[0]['attempts']);
    }

    public function testQueueDeleteReserved(): void
    {
        $queue = 'test';
        $jobId = Queue::push($queue, ['action' => 'QueueDeleteReserved'], 'test');

        Queue::deleteReserved($queue, $jobId, 0);
        $jobs = Queue::getDatabase()
            ->table(Config::get('queue.connections.database.table'))
            ->get();

        $this->assertCount(0, $jobs);
    }

    public function testQueueRelease(): void
    {
        Carbon::setTestNow();
        $queue = 'test';
        $delay = 123;
        Queue::push($queue, ['action' => 'QueueRelease'], 'test');

        $job           = Queue::pop($queue);
        $releasedJobId = Queue::release($queue, $job->getJobRecord(), $delay);

        $releasedJob = Queue::getDatabase()
            ->table(Config::get('queue.connections.database.table'))
            ->where('_id', $releasedJobId)
            ->first();

        $this->assertEquals($queue, $releasedJob['queue']);
        $this->assertEquals(1, $releasedJob['attempts']);
        $this->assertNull($releasedJob['reserved_at']);
        $this->assertEquals(
            Carbon::now()->addRealSeconds($delay)->getTimestamp(),
            $releasedJob['available_at'],
        );
        $this->assertEquals(Carbon::now()->getTimestamp(), $releasedJob['created_at']);
        $this->assertEquals($job->getRawBody(), $releasedJob['payload']);
    }

    public function testQueueDeleteAndRelease(): void
    {
        $queue = 'test';
        $delay = 123;
        Queue::push($queue, ['action' => 'QueueDeleteAndRelease'], 'test');
        $job = Queue::pop($queue);

        $mock = Mockery::mock(MongoQueue::class)->makePartial();
        $mock->expects('deleteReserved')->once()->with($queue, $job->getJobId());
        $mock->expects('release')->once()->with($queue, $job->getJobRecord(), $delay);

        $mock->deleteAndRelease($queue, $job, $delay);
    }

    public function testFailedJobLogging()
    {
        Carbon::setTestNow('2019-01-01 00:00:00');
        $provider = app('queue.failer');
        $provider->log('test_connection', 'test_queue', 'test_payload', new Exception('test_exception'));

        $failedJob = Queue::getDatabase()->table(Config::get('queue.failed.table'))->first();

        $this->assertSame('test_connection', $failedJob['connection']);
        $this->assertSame('test_queue', $failedJob['queue']);
        $this->assertSame('test_payload', $failedJob['payload']);
        $this->assertEquals(new UTCDateTime(Carbon::now()), $failedJob['failed_at']);
        $this->assertStringStartsWith('Exception: test_exception in ', $failedJob['exception']);
    }
}
