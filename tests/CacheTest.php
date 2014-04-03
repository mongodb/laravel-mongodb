<?php

class CacheTest extends TestCase {

	public function tearDown()
	{
		User::truncate();
		Cache::forget('db.users');
	}

	public function testCache()
	{
		User::create(array('name' => 'John Doe', 'age' => 35, 'title' => 'admin'));
		User::create(array('name' => 'Jane Doe', 'age' => 33, 'title' => 'admin'));
		User::create(array('name' => 'Harry Hoe', 'age' => 13, 'title' => 'user'));

		$users = DB::collection('users')->where('age', '>', 10)->remember(10)->get();
		$this->assertEquals(3, count($users));

		$users = DB::collection('users')->where('age', '>', 10)->getCached();
		$this->assertEquals(3, count($users));

		$users = User::where('age', '>', 10)->remember(10, 'db.users')->get();
		$this->assertEquals(3, count($users));

		$users = Cache::get('db.users');
		$this->assertEquals(3, count($users));
	}

}
