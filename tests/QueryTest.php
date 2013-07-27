<?php
require_once('vendor/autoload.php');
require_once('tests/app.php');

use Jenssegers\Mongodb\Facades\DB;

class QueryTest extends PHPUnit_Framework_TestCase {

	public function setUp() {}

	public function tearDown()
	{
		DB::collection('users')->truncate();
	}

	public function testCollection()
	{
		$this->assertInstanceOf('Jenssegers\Mongodb\Builder', DB::collection('users'));
	}

	public function testInsert()
	{
		$user = array('name' => 'John Doe');
		DB::collection('users')->insert($user);

		$users = DB::collection('users')->get();
		$this->assertEquals(1, count($users));

		$user = DB::collection('users')->first();
		$this->assertEquals('John Doe', $user['name']);
	}

	public function testFind()
	{
		$user = array('name' => 'John Doe');
		$id = DB::collection('users')->insertGetId($user);

		$this->assertNotNull($id);
		$this->assertTrue(is_string($id));

		$user = DB::collection('users')->find($id);
		$this->assertEquals('John Doe', $user['name']);
	}

}