<?php

class ConnectionTest extends TestCase {

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
		$this->assertInstanceOf('MongoDB', $connection->getMongoDB());

		$connection = DB::connection('mongodb');
		$this->assertInstanceOf('MongoClient', $connection->getMongoClient());
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

	public function testDynamic()
	{
		$dbs = DB::connection('mongodb')->listCollections();
		$this->assertTrue(is_array($dbs));
	}

	/*public function testMultipleConnections()
	{
		global $app;

		# Add fake host
		$db = $app['config']['database.connections']['mongodb'];
		$db['host'] = array($db['host'], '1.2.3.4');

		$connection = new Connection($db);
		$mongoclient = $connection->getMongoClient();

		$hosts = $mongoclient->getHosts();
		$this->assertEquals(1, count($hosts));
	}*/

	public function testQueryLog()
	{
		$this->assertEquals(0, count(DB::getQueryLog()));

		DB::collection('items')->get();
		$this->assertEquals(1, count(DB::getQueryLog()));

		DB::collection('items')->insert(array('name' => 'test'));
		$this->assertEquals(2, count(DB::getQueryLog()));

		DB::collection('items')->count();
		$this->assertEquals(3, count(DB::getQueryLog()));

		DB::collection('items')->where('name', 'test')->update(array('name' => 'test'));
		$this->assertEquals(4, count(DB::getQueryLog()));

		DB::collection('items')->where('name', 'test')->delete();
		$this->assertEquals(5, count(DB::getQueryLog()));
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
		Config::set('database.connections.mongodb.username', 'foo');
		Config::set('database.connections.mongodb.password', 'bar');
		$host = Config::get('database.connections.mongodb.host');
		$port = Config::get('database.connections.mongodb.port', 27017);
		$database = Config::get('database.connections.mongodb.database');

		$this->setExpectedException('MongoConnectionException', "Failed to connect to: $host:$port: Authentication failed on database '$database' with username 'foo': auth fails");
		$connection = DB::connection('mongodb');
	}

	public function testCustomPort()
	{
		$port = 27000;
		Config::set('database.connections.mongodb.port', $port);
		$host = Config::get('database.connections.mongodb.host');
		$database = Config::get('database.connections.mongodb.database');

		$this->setExpectedException('MongoConnectionException', "Failed to connect to: $host:$port: Connection refused");
		$connection = DB::connection('mongodb');
	}

}
