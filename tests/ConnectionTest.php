<?php

class ConnectionTest extends TestCase
{
    public function testConnection()
    {
        $connection = DB::connection('mongodb');
        $this->assertInstanceOf('Jenssegers\Mongodb\Connection', $connection);
    }

    public function testReconnect()
    {
        $c1 = DB::connection('mongodb');
        $c2 = DB::connection('mongodb');
        $this->assertEquals(spl_object_hash($c1), spl_object_hash($c2));

        $c1 = DB::connection('mongodb');
        DB::purge('mongodb');
        $c2 = DB::connection('mongodb');
        $this->assertNotEquals(spl_object_hash($c1), spl_object_hash($c2));
    }

    public function testDb()
    {
        $connection = DB::connection('mongodb');
        $this->assertInstanceOf('MongoDB\Database', $connection->getMongoDB());

        $connection = DB::connection('mongodb');
        $this->assertInstanceOf('MongoDB\Client', $connection->getMongoClient());
    }

    public function testCollection()
    {
        $collection = DB::connection('mongodb')->getCollection('unittest');
        $this->assertInstanceOf('Jenssegers\Mongodb\Collection', $collection);

        $collection = DB::connection('mongodb')->collection('unittests');
        $this->assertInstanceOf('Jenssegers\Mongodb\Query\Builder', $collection);

        $collection = DB::connection('mongodb')->table('unittests');
        $this->assertInstanceOf('Jenssegers\Mongodb\Query\Builder', $collection);
    }

    // public function testDynamic()
    // {
    //     $dbs = DB::connection('mongodb')->listCollections();
    //     $this->assertInternalType('array', $dbs);
    // }

    // public function testMultipleConnections()
    // {
    //     global $app;

    //     # Add fake host
    //     $db = $app['config']['database.connections']['mongodb'];
    //     $db['host'] = array($db['host'], '1.2.3.4');

    //     $connection = new Connection($db);
    //     $mongoclient = $connection->getMongoClient();

    //     $hosts = $mongoclient->getHosts();
    //     $this->assertCount(1, $hosts);
    // }

    public function testQueryLog()
    {
        DB::enableQueryLog();

        $this->assertCount(0, DB::getQueryLog());

        DB::collection('items')->get();
        $this->assertCount(1, DB::getQueryLog());

        DB::collection('items')->insert(['name' => 'test']);
        $this->assertCount(2, DB::getQueryLog());

        DB::collection('items')->count();
        $this->assertCount(3, DB::getQueryLog());

        DB::collection('items')->where('name', 'test')->update(['name' => 'test']);
        $this->assertCount(4, DB::getQueryLog());

        DB::collection('items')->where('name', 'test')->delete();
        $this->assertCount(5, DB::getQueryLog());
    }

    public function testSchemaBuilder()
    {
        $schema = DB::connection('mongodb')->getSchemaBuilder();
        $this->assertInstanceOf('Jenssegers\Mongodb\Schema\Builder', $schema);
    }

    public function testDriverName()
    {
        $driver = DB::connection('mongodb')->getDriverName();
        $this->assertEquals('mongodb', $driver);
    }

    public function testAuth()
    {
        $host = Config::get('database.connections.mongodb.host');
        Config::set('database.connections.mongodb.username', 'foo');
        Config::set('database.connections.mongodb.password', 'bar');
        Config::set('database.connections.mongodb.options.database', 'custom');

        $connection = DB::connection('mongodb');
        $this->assertEquals('mongodb://' . $host . '/custom', (string) $connection->getMongoClient());
    }

    public function testCustomHostAndPort()
    {
        Config::set('database.connections.mongodb.host', 'db1');
        Config::set('database.connections.mongodb.port', 27000);

        $connection = DB::connection('mongodb');
        $this->assertEquals("mongodb://db1:27000", (string) $connection->getMongoClient());
    }

    public function testHostWithPorts()
    {
        Config::set('database.connections.mongodb.port', 27000);
        Config::set('database.connections.mongodb.host', ['db1:27001', 'db2:27002', 'db3:27000']);

        $connection = DB::connection('mongodb');
        $this->assertEquals('mongodb://db1:27001,db2:27002,db3:27000', (string) $connection->getMongoClient());
    }
}
