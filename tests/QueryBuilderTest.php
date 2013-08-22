<?php
require_once('tests/app.php');

use Illuminate\Support\Facades\DB;

class QueryBuilderTest extends PHPUnit_Framework_TestCase {

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
		DB::collection('users')->insert(array(
			'tags' => array('tag1', 'tag2'),
			'name' => 'John Doe',
		));

		$users = DB::collection('users')->get();
		$this->assertEquals(1, count($users));

		$user = $users[0];
		$this->assertEquals('John Doe', $user['name']);
		$this->assertTrue(is_array($user['tags']));
	}

	public function testInsertGetId() 
	{
		$id = DB::collection('users')->insertGetId(array('name' => 'John Doe'));

		$this->assertTrue(is_string($id));
		$this->assertEquals(24, strlen($id));
	}

	public function testBatchInsert()
	{
		DB::collection('users')->insert(array(
			array(
				'tags' => array('tag1', 'tag2'),
				'name' => 'Jane Doe',	
			),
			array(
				'tags' => array('tag3'),
				'name' => 'John Doe',
			),
		));

		$users = DB::collection('users')->get();
		$this->assertEquals(2, count($users));

		$user = $users[0];
		$this->assertEquals('Jane Doe', $user['name']);
		$this->assertTrue(is_array($user['tags']));
	}

	public function testFind()
	{
		$id = DB::collection('users')->insertGetId(array('name' => 'John Doe'));

		$user = DB::collection('users')->find($id);
		$this->assertEquals('John Doe', $user['name']);
	}

	public function testCount()
	{
		DB::collection('users')->insert(array(
			array('name' => 'Jane Doe'),
			array('name' => 'John Doe')
		));

		$this->assertEquals(2, DB::collection('users')->count());
	}

	public function testUpdate()
	{
		DB::collection('users')->insert(array(
			array('name' => 'Jane Doe', 'age' => 20),
			array('name' => 'John Doe', 'age' => 21)
		));

		DB::collection('users')->where('name', 'John Doe')->update(array('age' => 100));
		$users = DB::collection('users')->get();

		$this->assertEquals(20, $users[0]['age']);
		$this->assertEquals(100, $users[1]['age']);
	}

	public function testDelete()
	{
		DB::collection('users')->insert(array(
			array('name' => 'Jane Doe', 'age' => 20),
			array('name' => 'John Doe', 'age' => 25)
		));

		DB::collection('users')->where('age', '<', 10)->delete();
		$this->assertEquals(2, DB::collection('users')->count());

		DB::collection('users')->where('age', '<', 25)->delete();
		$this->assertEquals(1, DB::collection('users')->count());
	}

	public function testTruncate()
	{
		DB::collection('users')->insert(array('name' => 'John Doe'));
		DB::collection('users')->truncate();
		$this->assertEquals(0, DB::collection('users')->count());
	}

	public function testSubKey()
	{
		DB::collection('users')->insert(array(
			array(
				'name' => 'John Doe',
				'address' => array('country' => 'Belgium', 'city' => 'Ghent')
			),
			array(
				'name' => 'Jane Doe',
				'address' => array('country' => 'France', 'city' => 'Paris')
			)
		));

		$users = DB::collection('users')->where('address.country', 'Belgium')->get();
		$this->assertEquals(1, count($users));
		$this->assertEquals('John Doe', $users[0]['name']);
	}

	public function testInArray()
	{
		DB::collection('items')->insert(array(
			array(
				'tags' => array('tag1', 'tag2', 'tag3', 'tag4')
			),
			array(
				'tags' => array('tag2')
			)
		));

		$items = DB::collection('items')->where('tags', 'tag2')->get();
		$this->assertEquals(2, count($items));

		$items = DB::collection('items')->where('tags', 'tag1')->get();
		$this->assertEquals(1, count($items));
	}

	public function testRaw()
	{
		DB::collection('users')->insert(array(
			array('name' => 'Jane Doe', 'age' => 20),
			array('name' => 'John Doe', 'age' => 25)
		));

		$cursor = DB::collection('users')->raw(function($collection) {
			return $collection->find(array('age' => 20));
		});

		$this->assertInstanceOf('MongoCursor', $cursor);
		$this->assertEquals(1, $cursor->count());

		$collection = DB::collection('users')->raw();
		$this->assertInstanceOf('MongoCollection', $collection);
	}

	public function testPush()
	{
		$id = DB::collection('users')->insertGetId(array(
			'name' => 'John Doe',
			'tags' => array()
		));

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
		$id = DB::collection('users')->insertGetId(array(
			'name' => 'John Doe',
			'tags' => array('tag1', 'tag2', 'tag3', 'tag4')
		));

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

	public function testTake()
	{
		DB::collection('items')->insert(array(
			array('name' => 'knife', 'type' => 'sharp', 'amount' => 34),
			array('name' => 'fork',  'type' => 'sharp', 'amount' => 20),
			array('name' => 'spoon', 'type' => 'round', 'amount' => 3),
			array('name' => 'spoon', 'type' => 'round', 'amount' => 14)
		));

		$items = DB::collection('items')->take(2)->get();
		$this->assertEquals(2, count($items));
		$this->assertEquals('knife', $items[0]['name']);
	}

	public function testSkip()
	{
		DB::collection('items')->insert(array(
			array('name' => 'knife', 'type' => 'sharp', 'amount' => 34),
			array('name' => 'fork',  'type' => 'sharp', 'amount' => 20),
			array('name' => 'spoon', 'type' => 'round', 'amount' => 3),
			array('name' => 'spoon', 'type' => 'round', 'amount' => 14)
		));

		$items = DB::collection('items')->skip(2)->get();
		$this->assertEquals(2, count($items));
		$this->assertEquals('spoon', $items[0]['name']);
	}

	public function testPluck()
	{
		DB::collection('users')->insert(array(
			array('name' => 'Jane Doe', 'age' => 20),
			array('name' => 'John Doe', 'age' => 25)
		));

		$age = DB::collection('users')->where('name', 'John Doe')->pluck('age');
		$this->assertEquals(25, $age);
	}

	public function testList()
	{
		DB::collection('items')->insert(array(
			array('name' => 'knife', 'type' => 'sharp', 'amount' => 34),
			array('name' => 'fork',  'type' => 'sharp', 'amount' => 20),
			array('name' => 'spoon', 'type' => 'round', 'amount' => 3),
			array('name' => 'spoon', 'type' => 'round', 'amount' => 14)
		));

		$list = DB::collection('items')->lists('name');
		$this->assertEquals(array('knife', 'fork', 'spoon', 'spoon'), $list);

		$list = DB::collection('items')->lists('type', 'name');
		$this->assertEquals(array('knife' => 'sharp', 'fork' => 'sharp', 'spoon' => 'round'), $list);
	}

}