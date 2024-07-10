<?php

namespace MongoDB\Laravel\Tests;

use Illuminate\Session\DatabaseSessionHandler;
use Illuminate\Support\Facades\DB;

class SessionTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::connection('mongodb')->getCollection('sessions')->drop();

        parent::tearDown();
    }

    public function testDatabaseSessionHandler()
    {
        $sessionId = '123';

        $handler = new DatabaseSessionHandler(
            $this->app['db']->connection('mongodb'),
            'sessions',
            10,
        );

        $handler->write($sessionId, 'foo');
        $this->assertEquals('foo', $handler->read($sessionId));

        $handler->write($sessionId, 'bar');
        $this->assertEquals('bar', $handler->read($sessionId));
    }
}
