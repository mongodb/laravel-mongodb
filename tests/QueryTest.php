<?php
require_once('vendor/autoload.php');
require_once('models/User.php');

use Jenssegers\Mongodb\Facades\MongoDB;

class QueryTest extends PHPUnit_Framework_TestCase {

	public function setUp()
	{
		include('tests/app.php');
	}

	public function tearDown()
	{
		MongoDB::collection('users')->truncate();
	}

	public function testCollection()
	{
		$this->assertInstanceOf('Jenssegers\Mongodb\Builder', MongoDB::collection('users'));
	}

	public function testInsert()
	{
		$user = array('name' => 'John Doe');
		MongoDB::collection('users')->insert($user);

		$users = MongoDB::collection('users')->get();
		$this->assertEquals(1, count($users));

		$user = MongoDB::collection('users')->first();
		$this->assertEquals('John Doe', $user['name']);
	}

	public function testFind()
	{
		$user = array('name' => 'John Doe');
		$id = MongoDB::collection('users')->insertGetId($user);

		$this->assertNotNull($id);
		$this->assertTrue(is_string($id));

		$user = MongoDB::collection('users')->find($id);
		$this->assertEquals('John Doe', $user['name']);
	}

}