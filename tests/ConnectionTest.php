<?php
require_once('vendor/autoload.php');
require_once('tests/app.php');

use Jenssegers\Mongodb\Facades\DB;
use Jenssegers\Mongodb\Connection;

class ConnectionTest extends PHPUnit_Framework_TestCase {

	public function setUp() {}

	public function tearDown() {}

	public function testConnection()
	{
		$connection = DB::connection('mongodb');
		$this->assertInstanceOf('Jenssegers\Mongodb\Connection', $connection);

		$c1 = DB::connection('mongodb');
		$c2 = DB::connection('mongodb');
		$this->assertEquals($c1, $c2);

		$c1 = DB::connection('mongodb');
		$c2 = DB::reconnect('mongodb');
		$this->assertNotEquals($c1, $c2);
	}

	public function testDb()
	{
		$connection = DB::connection('mongodb');
		$this->assertInstanceOf('MongoDB', $connection->getDb());
	}

	public function testCollection()
	{
		$collection = DB::connection('mongodb')->getCollection('unittest');
		$this->assertInstanceOf('MongoCollection', $collection);

		$collection = DB::connection('mongodb')->collection('unittests');
		$this->assertInstanceOf('Jenssegers\Mongodb\Builder', $collection);

		$collection = DB::connection('mongodb')->table('unittests');
		$this->assertInstanceOf('Jenssegers\Mongodb\Builder', $collection);
	}

	public function testDynamic()
	{
		$dbs = DB::connection('mongodb')->listCollections();
		$this->assertTrue(is_array($dbs));
	}

}