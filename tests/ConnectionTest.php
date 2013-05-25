<?php
require_once('vendor/autoload.php');

use Jenssegers\Mongodb\Connection;

class ConnectionTest extends PHPUnit_Framework_TestCase {

	private $collection;
	private $connection;

	public function setUp()
	{
		include('tests/app.php');
		$this->connection = new Connection($app['config']['database.connections']['mongodb']);
		$this->collection = $this->connection->getCollection('unittest');
	}

	public function tearDown()
	{
		if ($this->collection)
		{
			$this->collection->drop();
		}
	}

	public function testDb()
	{
		$db = $this->connection->getDb();
		$this->assertInstanceOf('MongoDB', $db);
	}

	public function testCollection()
	{
		$this->assertInstanceOf('MongoCollection', $this->collection);
		$this->assertEquals('unittest', $this->collection->getName());
	}

}