<?php
require_once('vendor/autoload.php');
require_once('models/User.php');

use Jenssegers\Mongodb\Facades\DB;

class CacheTest extends PHPUnit_Framework_TestCase {

	protected $app;

	public function setUp()
	{
		include('tests/app.php');
		$this->app = $app;

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
		# auto generate cache key
		$users = DB::collection('users')->where('age', '>', 10)->remember(10)->get();
		$this->assertEquals(3, count($users));

		$users = DB::collection('users')->where('age', '>', 10)->getCached();
		$this->assertEquals(3, count($users));

		# store under predefined cache key
		$users = User::where('age', '>', 10)->remember(10, 'db.users')->get();
		$this->assertEquals(3, count($users));

		# get from cache driver
		$cache = $this->app['cache'];
		$users = $cache->get('db.users');
		$this->assertEquals(3, count($users));
	}

}