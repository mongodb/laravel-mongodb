<?php

class QueueTest extends TestCase
{
    public function testQueue()
    {
        $id = Queue::push('test', ['foo' => 'bar'], 'test');
        $this->assertNotNull($id);

        $job = Queue::pop('test');
        $this->assertInstanceOf('Illuminate\Queue\Jobs\DatabaseJob', $job);
    }
}
