<?php
declare(strict_types=1);

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
        $id = Queue::push('test', ['action' => 'QueueJobLifeCycle'], 'test');
        $this->assertNotNull($id);

        // Get and reserve the test job (next available)
        $job = Queue::pop('test');
        $this->assertInstanceOf(Jenssegers\Mongodb\Queue\MongoJob::class, $job);
        $this->assertEquals(1, $job->isReserved());
        $this->assertEquals(json_encode([
            'displayName' => 'test',
            'job' => 'test',
            'maxTries' => null,
            'delay' => null,
            'timeout' => null,
            'data' => ['action' => 'QueueJobLifeCycle'],
        ]), $job->getRawBody());

        // Remove reserved job
        $job->delete();
        $this->assertEquals(0, Queue::getDatabase()->table(Config::get('queue.connections.database.table'))->count());
    }

    public function testQueueJobExpired(): void
    {
        $id = Queue::push('test', ['action' => 'QueueJobExpired'], 'test');
        $this->assertNotNull($id);

        // Expire the test job
        $expiry = \Carbon\Carbon::now()->subSeconds(Config::get('queue.connections.database.expire'))->getTimestamp();
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

    public function testFindFailJobNull(): void
    {
        Config::set('queue.failed.database', 'mongodb');
        $provider = app('queue.failer');

        $this->assertNull($provider->find(1));
    }

    public function testIncrementAttempts(): void
    {
        $job_id = Queue::push('test1', ['action' => 'QueueJobExpired'], 'test');
        $this->assertNotNull($job_id);
        $job_id = Queue::push('test2', ['action' => 'QueueJobExpired'], 'test');
        $this->assertNotNull($job_id);

        $job = Queue::pop('test');
        $this->assertEquals(1, $job->attempts());
        $job->delete();

        $others_jobs = Queue::getDatabase()
            ->table(Config::get('queue.connections.database.table'))
            ->get();

        $this->assertCount(1, $others_jobs);
        $this->assertEquals(0, $others_jobs[0]['attempts']);
    }
}
