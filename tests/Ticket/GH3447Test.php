<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Ticket;

use Illuminate\Support\Facades\DB;
use MongoDB\Client;
use MongoDB\Laravel\Connection;
use MongoDB\Laravel\Tests\TestCase;

/**
 * DatabaseMigrations calls disconnect() on the connection after wiping the
 * database. Anything that needs the client afterwards must reconnect.
 *
 * @see https://github.com/mongodb/laravel-mongodb/issues/3447
 */
class GH3447Test extends TestCase
{
    public function tearDown(): void
    {
        DB::connection('mongodb')->disableQueryLog();
        DB::connection('mongodb')->table('items')->truncate();

        parent::tearDown();
    }

    public function testEnableQueryLogAfterDisconnect(): void
    {
        $connection = DB::connection('mongodb');
        $this->assertInstanceOf(Connection::class, $connection);

        $connection->disconnect();
        $connection->enableQueryLog();

        $this->assertCount(0, $connection->getQueryLog());

        $connection->table('items')->get();

        $this->assertCount(1, $logs = $connection->getQueryLog());
        $this->assertJsonStringEqualsJsonString('{"find":"items","filter":{}}', $logs[0]['query']);
    }

    public function testQueryLogStaysEnabledAcrossDisconnect(): void
    {
        $connection = DB::connection('mongodb');
        $this->assertInstanceOf(Connection::class, $connection);

        $connection->enableQueryLog();
        $connection->table('items')->get();
        $this->assertCount(1, $connection->getQueryLog());

        $connection->disconnect();

        $this->assertTrue($connection->logging());

        $connection->table('items')->insert(['name' => 'test']);
        $this->assertCount(2, $connection->getQueryLog());

        // Disabling after the reconnect detaches the subscriber from the new client
        $connection->disableQueryLog();
        $connection->table('items')->get();
        $this->assertCount(2, $connection->getQueryLog());
    }

    public function testClientAndDatabaseAreRecreatedAfterDisconnect(): void
    {
        $connection = DB::connection('mongodb');
        $this->assertInstanceOf(Connection::class, $connection);

        $client = $connection->getClient();
        $database = $connection->getDatabase();
        $this->assertInstanceOf(Client::class, $client);

        $connection->disconnect();

        $this->assertNotSame($client, $connection->getClient());
        $this->assertNotSame($database, $connection->getDatabase());
        $this->assertSame($database->getDatabaseName(), $connection->getDatabase()->getDatabaseName());

        // Queries, ping and transactions sessions use the new client
        $connection->ping();
        $connection->table('items')->insert(['name' => 'after-disconnect']);
        $this->assertSame(1, $connection->table('items')->count());
    }

    public function testDisconnectTwiceIsIdempotent(): void
    {
        $connection = DB::connection('mongodb');
        $this->assertInstanceOf(Connection::class, $connection);

        $connection->enableQueryLog();
        $connection->disconnect();
        $connection->disconnect();

        $connection->table('items')->get();
        $this->assertCount(1, $connection->getQueryLog());
    }
}
