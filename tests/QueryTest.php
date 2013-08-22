<?php
require_once('tests/app.php');

use Illuminate\Support\Facades\DB;

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

	public function testGet()
	{
		$users = DB::collection('users')->get();
		$this->assertEquals(0, count($users));

		DB::collection('users')->insert(array('name' => 'John Doe'));

		$users = DB::collection('users')->get();
		$this->assertEquals(1, count($users));
	}

	public function testInsert()
	{
		$user = array(
			'tags' => array('tag1', 'tag2'),
			'name' => 'John Doe',
		);
		DB::collection('users')->insert($user);

		$users = DB::collection('users')->get();
		$this->assertEquals(1, count($users));

		$user = $users[0];
		$this->assertEquals('John Doe', $user['name']);
		$this->assertTrue(is_array($user['tags']));
	}

	public function testBatchInsert()
	{
		$users = array(
			array(
				'tags' => array('tag1', 'tag2'),
				'name' => 'Jane Doe',	
			),
			array(
				'tags' => array('tag3'),
				'name' => 'John Doe',
			),
		);
		DB::collection('users')->insert($users);

		$users = DB::collection('users')->get();
		$this->assertEquals(2, count($users));

		$user = $users[0];
		$this->assertEquals('Jane Doe', $user['name']);
		$this->assertTrue(is_array($user['tags']));
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

	public function testUpdate()
	{
		DB::collection('users')->insert(array('name' => 'John Doe', 'age' => 10));
		DB::collection('users')->where('name', 'John Doe')->update(array('age' => 100));

        $user = User::where('name', 'John Doe')->first();
		$this->assertEquals(100, $user->age);
	}

	public function testDelete()
	{
		DB::collection('users')->insert(array('name' => 'John Doe', 'age' => 100));

		DB::collection('users')->where('age', '<', 30)->delete();
		$this->assertEquals(1, DB::collection('users')->count());

		DB::collection('users')->where('age', '>', 30)->delete();
		$this->assertEquals(0, DB::collection('users')->count());
	}

	public function testTruncate()
	{
		DB::collection('users')->insert(array('name' => 'John Doe', 'age' => 100));
		DB::collection('users')->truncate();
		$this->assertEquals(0, DB::collection('users')->count());
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

	public function testDistinct()
	{
		DB::collection('items')->insert(array(
			array('name' => 'knife', 'type' => 'sharp', 'amount' => 34),
			array('name' => 'fork',  'type' => 'sharp', 'amount' => 20),
			array('name' => 'spoon', 'type' => 'round', 'amount' => 3),
			array('name' => 'spoon', 'type' => 'round', 'amount' => 14)
		));

		$items = DB::collection('items')->distinct('name')->get();
		$this->assertEquals(array('knife', 'fork', 'spoon'), $items);

		$types = DB::collection('items')->distinct('type')->get();
		$this->assertEquals(array('sharp', 'round'), $types);
	}

	public function testCustomId()
	{
		DB::collection('items')->insert(array(
			array('_id' => 'knife', 'type' => 'sharp', 'amount' => 34),
			array('_id' => 'fork',  'type' => 'sharp', 'amount' => 20),
			array('_id' => 'spoon', 'type' => 'round', 'amount' => 3)
		));

		$item = DB::collection('items')->find('knife');
		$this->assertEquals('knife', $item['_id']);

		$item = DB::collection('items')->where('_id', 'fork')->first();
		$this->assertEquals('fork', $item['_id']);
	}

}