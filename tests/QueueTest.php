<?php

class QueueTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        // Always start with a clean slate
        Queue::getDatabase()->table(Config::get('queue.connections.database.table'))->truncate();
        Queue::getDatabase()->table(Config::get('queue.failed.table'))->truncate();
    }

    public function testQueueJobLifeCycle()
    {
        $id = Queue::push('test', ['action' => 'QueueJobLifeCycle'], 'test');
        $this->assertNotNull($id);

        // Get and reserve the test job (next available)
        $job = Queue::pop('test');
        $this->assertInstanceOf('Illuminate\Queue\Jobs\DatabaseJob', $job);
        $this->assertEquals(1, $job->getDatabaseJob()->reserved);
        $this->assertEquals(json_encode(['job' => 'test', 'data' => ['action' => 'QueueJobLifeCycle']]), $job->getRawBody());

        // Remove reserved job
        $job->delete();
        $this->assertEquals(0, Queue::getDatabase()->table(Config::get('queue.connections.database.table'))->count());
    }

    public function testQueueJobExpired()
    {
        $id = Queue::push('test', ['action' => 'QueueJobExpired'], 'test');
        $this->assertNotNull($id);

        // Expire the test job
        $expiry = \Carbon\Carbon::now()->subSeconds(Config::get('queue.connections.database.expire'))->getTimestamp();
        Queue::getDatabase()->table(Config::get('queue.connections.database.table'))->where('_id', $id)->update(['reserved' => 1, 'reserved_at' => $expiry]);

        // Expect an attempted older job in the queue
        $job = Queue::pop('test');
        $this->assertEquals(1, $job->getDatabaseJob()->attempts);
        $this->assertGreaterThan($expiry, $job->getDatabaseJob()->reserved_at);

        $job->delete();
        $this->assertEquals(0, Queue::getDatabase()->table(Config::get('queue.connections.database.table'))->count());
    }
}
