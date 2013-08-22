<?php
require_once('tests/app.php');

use Illuminate\Support\Facades\DB;

class CacheTest extends PHPUnit_Framework_TestCase {

	protected $cache;

	public function setUp()
	{
		global $app;
		$this->cache = $app['cache'];

		User::create(array('name' => 'John Doe', 'age' => 35, 'title' => 'admin'));
		User::create(array('name' => 'Jane Doe', 'age' => 33, 'title' => 'admin'));
		User::create(array('name' => 'Harry Hoe', 'age' => 13, 'title' => 'user'));
	}

	public function tearDown()
	{
		User::truncate();
		$this->cache->forget('db.users');
	}

	public function testCache()
	{
		$users = DB::collection('users')->where('age', '>', 10)->remember(10)->get();
		$this->assertEquals(3, count($users));

		$users = DB::collection('users')->where('age', '>', 10)->getCached();
		$this->assertEquals(3, count($users));

		$users = User::where('age', '>', 10)->remember(10, 'db.users')->get();
		$this->assertEquals(3, count($users));

		$users = $this->cache->get('db.users');
		$this->assertEquals(3, count($users));
	}

}