<?php
require_once('tests/app.php');

use Jenssegers\Mongodb\Facades\DB;

class QueryTest extends PHPUnit_Framework_TestCase {

	public function setUp() {}

	public function tearDown()
	{
		DB::collection('users')->truncate();
		DB::collection('items')->truncate();
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

	public function testSubKey()
	{
		$user1 = array(
			'name' => 'John Doe',
			'address' => array(
				'country' => 'Belgium',
				'city' => 'Ghent'
				)
			);

		$user2 = array(
			'name' => 'Jane Doe',
			'address' => array(
				'country' => 'France',
				'city' => 'Paris'
				)
			);

		DB::collection('users')->insert(array($user1, $user2));

		$users = DB::collection('users')->where('address.country', 'Belgium')->get();
		$this->assertEquals(1, count($users));
		$this->assertEquals('John Doe', $users[0]['name']);
	}

	public function testInArray()
	{
		$item1 = array(
			'tags' => array('tag1', 'tag2', 'tag3', 'tag4')
			);

		$item2 = array(
			'tags' => array('tag2')
			);

		DB::collection('items')->insert(array($item1, $item2));

		$items = DB::collection('items')->where('tags', 'tag2')->get();
		$this->assertEquals(2, count($items));

		$items = DB::collection('items')->where('tags', 'tag1')->get();
		$this->assertEquals(1, count($items));
	}

	public function testRaw()
	{
		DB::collection('users')->insert(array('name' => 'John Doe'));

		$cursor = DB::collection('users')->raw(function($collection) {
			return $collection->find();
		});

		$this->assertInstanceOf('MongoCursor', $cursor);
		$this->assertEquals(1, $cursor->count());

		$collection = DB::collection('users')->raw();
		$this->assertInstanceOf('MongoCollection', $collection);
	}

	public function testPush()
	{
		$user = array('name' => 'John Doe', 'tags' => array());
		$id = DB::collection('users')->insertGetId($user);

		DB::collection('users')->where('_id', $id)->push('tags', 'tag1');
		$user = DB::collection('users')->find($id);

		$this->assertTrue(is_array($user['tags']));
		$this->assertEquals(1, count($user['tags']));
		$this->assertEquals('tag1', $user['tags'][0]);

		DB::collection('users')->where('_id', $id)->push('tags', 'tag2');
		$user = DB::collection('users')->find($id);

		$this->assertTrue(is_array($user['tags']));
		$this->assertEquals(2, count($user['tags']));
		$this->assertEquals('tag2', $user['tags'][1]);

		DB::collection('users')->where('_id', $id)->push('tags', array('tag3', 'tag4'));
		$user = DB::collection('users')->find($id);

		$this->assertTrue(is_array($user['tags']));
		$this->assertEquals(4, count($user['tags']));
		$this->assertEquals('tag4', $user['tags'][3]);
	}

	public function testPull()
	{
		$user = array('name' => 'John Doe', 'tags' => array('tag1', 'tag2', 'tag3', 'tag4'));
		$id = DB::collection('users')->insertGetId($user);

		DB::collection('users')->where('_id', $id)->pull('tags', 'tag3');
		$user = DB::collection('users')->find($id);

		$this->assertTrue(is_array($user['tags']));
		$this->assertEquals(3, count($user['tags']));
		$this->assertEquals('tag4', $user['tags'][2]);

		DB::collection('users')->where('_id', $id)->pull('tags', array('tag2', 'tag4'));
		$user = DB::collection('users')->find($id);

		$this->assertTrue(is_array($user['tags']));
		$this->assertEquals(1, count($user['tags']));
		$this->assertEquals('tag1', $user['tags'][0]);
	}

}