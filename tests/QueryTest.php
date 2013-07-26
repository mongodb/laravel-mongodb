<?php
require_once('vendor/autoload.php');
require_once('models/User.php');

use Jenssegers\Mongodb\Facades\Mongo;

class QueryTest extends PHPUnit_Framework_TestCase {

	public function setUp()
	{
		include('tests/app.php');
	}

	public function tearDown()
	{
		Mongo::collection('users')->truncate();
	}

	public function testCollection()
	{
		$this->assertInstanceOf('Jenssegers\Mongodb\Builder', Mongo::collection('users'));
	}

	public function testInsert()
	{
		$user = array('name' => 'John Doe');
		Mongo::collection('users')->insert($user);

		$users = Mongo::collection('users')->get();
		$this->assertEquals(1, count($users));

		$user = Mongo::collection('users')->first();
		$this->assertEquals('John Doe', $user['name']);
	}

	public function testFind()
	{
		$user = array('name' => 'John Doe');
		$id = Mongo::collection('users')->insertGetId($user);

		$this->assertNotNull($id);
		$this->assertTrue(is_string($id));

		$user = Mongo::collection('users')->find($id);
		$this->assertEquals('John Doe', $user['name']);
	}

}