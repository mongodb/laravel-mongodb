<?php
require_once('vendor/autoload.php');

use Jenssegers\Mongodb\Connection;

class ConnectionTest extends PHPUnit_Framework_TestCase {

	private $connection;

	public function setUp()
	{
		include('tests/app.php');
		$this->connection = new Connection($app['config']['database.connections']['mongodb']);
	}

	public function tearDown()
	{
	}

	public function testDb()
	{
		$db = $this->connection->getDb();
		$this->assertInstanceOf('MongoDB', $db);
	}

	public function testCollection()
	{
		$collection = $this->connection->getCollection('unittest');
		$this->assertInstanceOf('MongoCollection', $collection);

		$collection = $this->connection->collection('unittests');
		$this->assertInstanceOf('Jenssegers\Mongodb\Builder', $collection);

		$collection = $this->connection->table('unittests');
		$this->assertInstanceOf('Jenssegers\Mongodb\Builder', $collection);
	}

	public function testDynamic()
	{
		$dbs = $this->connection->listDBs();
		$this->assertTrue(is_array($dbs));
	}

}