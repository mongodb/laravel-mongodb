<?php
require_once('tests/app.php');

use Illuminate\Support\Facades\DB;

class CacheTest extends PHPUnit_Framework_TestCase {

	public function setUp()
	{
		// test data
		User::create(array('name' => 'John Doe', 'age' => 35, 'title' => 'admin'));
		User::create(array('name' => 'Jane Doe', 'age' => 33, 'title' => 'admin'));
		User::create(array('name' => 'Harry Hoe', 'age' => 13, 'title' => 'user'));
	}

	public function tearDown()
	{
		User::truncate();
	}

	public function testCache()
	{
		# get from cache driver
		global $app;
		$cache = $app['cache'];
		$cache->forget('db.users');

		# auto generate cache key
		$users = DB::collection('users')->where('age', '>', 10)->remember(10)->get();
		$this->assertEquals(3, count($users));

		$users = DB::collection('users')->where('age', '>', 10)->getCached();
		$this->assertEquals(3, count($users));

		# store under predefined cache key
		$users = User::where('age', '>', 10)->remember(10, 'db.users')->get();
		$this->assertEquals(3, count($users));

		$users = $cache->get('db.users');
		$this->assertEquals(3, count($users));
	}

}