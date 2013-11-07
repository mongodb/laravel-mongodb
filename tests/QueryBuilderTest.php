<?php

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

	public function testNoDocument()
	{
		$items = DB::collection('items')->where('name', 'nothing')->get();
		$this->assertEquals(array(), $items);

		$item = DB::collection('items')->where('name', 'nothing')->first();
		$this->assertEquals(null, $item);

		$item = DB::collection('items')->where('_id', '51c33d8981fec6813e00000a')->first();
		$this->assertEquals(null, $item);
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

	public function testFindNull()
	{
		$user = DB::collection('users')->find(null);
		$this->assertEquals(null, $user);
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
			'tags' => array(),
			'messages' => array(),
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

		$message = array('from' => 'Jane', 'body' => 'Hi John');
		DB::collection('users')->where('_id', $id)->push('messages', $message);

		$user = DB::collection('users')->find($id);
		$this->assertTrue(is_array($user['messages']));
		$this->assertEquals($message, $user['messages'][0]);
	}

	public function testPull()
	{
		$message = array('from' => 'Jane', 'body' => 'Hi John');

		$id = DB::collection('users')->insertGetId(array(
			'name' => 'John Doe',
			'tags' => array('tag1', 'tag2', 'tag3', 'tag4'),
			'messages' => array($message)
		));

		DB::collection('users')->where('_id', $id)->pull('tags', 'tag3');

		$user = DB::collection('users')->find($id);
		$this->assertTrue(is_array($user['tags']));
		$this->assertEquals(3, count($user['tags']));
		$this->assertEquals('tag4', $user['tags'][2]);

		DB::collection('users')->where('_id', $id)->pull('messages', $message);

		$user = DB::collection('users')->find($id);
		$this->assertTrue(is_array($user['messages']));
		$this->assertEquals(0, count($user['messages']));
	}

	public function testDistinct()
	{
		DB::collection('items')->insert(array(
			array('name' => 'knife', 'type' => 'sharp',),
			array('name' => 'fork',  'type' => 'sharp'),
			array('name' => 'spoon', 'type' => 'round'),
			array('name' => 'spoon', 'type' => 'round')
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

		DB::collection('users')->insert(array(
			array('_id' => 1, 'name' => 'Jane Doe'),
			array('_id' => 2, 'name' => 'John Doe')
		));

		$item = DB::collection('users')->find(1);
		$this->assertEquals(1, $item['_id']);
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

	public function testAggregate()
	{
		DB::collection('items')->insert(array(
			array('name' => 'knife', 'type' => 'sharp', 'amount' => 34),
			array('name' => 'fork',  'type' => 'sharp', 'amount' => 20),
			array('name' => 'spoon', 'type' => 'round', 'amount' => 3),
			array('name' => 'spoon', 'type' => 'round', 'amount' => 14)
		));

		$this->assertEquals(71, DB::collection('items')->sum('amount'));
		$this->assertEquals(4, DB::collection('items')->count('amount'));
		$this->assertEquals(3, DB::collection('items')->min('amount'));
		$this->assertEquals(34, DB::collection('items')->max('amount'));
		$this->assertEquals(17.75, DB::collection('items')->avg('amount'));

		$this->assertEquals(2, DB::collection('items')->where('name', 'spoon')->count('amount'));
		$this->assertEquals(14, DB::collection('items')->where('name', 'spoon')->max('amount'));
	}

	public function testSubdocumentAggregate()
	{
		DB::collection('items')->insert(array(
			array('name' => 'knife', 'amount' => array('hidden' => 10, 'found' => 3)),
			array('name' => 'fork',  'amount' => array('hidden' => 35, 'found' => 12)),
			array('name' => 'spoon', 'amount' => array('hidden' => 14, 'found' => 21)),
			array('name' => 'spoon', 'amount' => array('hidden' => 6, 'found' => 4))
		));

		$this->assertEquals(65, DB::collection('items')->sum('amount.hidden'));
		$this->assertEquals(4, DB::collection('items')->count('amount.hidden'));
		$this->assertEquals(6, DB::collection('items')->min('amount.hidden'));
		$this->assertEquals(35, DB::collection('items')->max('amount.hidden'));
		$this->assertEquals(16.25, DB::collection('items')->avg('amount.hidden'));
	}

	public function testUpsert()
	{
		DB::collection('items')->where('name', 'knife')
							   ->update(
							   		array('amount' => 1),
							   		array('upsert' => true)
							   	);

		$this->assertEquals(1, DB::collection('items')->count());
	}

	public function testUnset()
	{
		$id1 = DB::collection('users')->insertGetId(array('name' => 'John Doe', 'note1' => 'ABC', 'note2' => 'DEF'));
		$id2 = DB::collection('users')->insertGetId(array('name' => 'Jane Doe', 'note1' => 'ABC', 'note2' => 'DEF'));

		DB::collection('users')->where('name', 'John Doe')->unset('note1');

		$user1 = DB::collection('users')->find($id1);
		$user2 = DB::collection('users')->find($id2);

		$this->assertFalse(isset($user1['note1']));
		$this->assertTrue(isset($user1['note2']));
		$this->assertTrue(isset($user2['note1']));
		$this->assertTrue(isset($user2['note2']));

		DB::collection('users')->where('name', 'Jane Doe')->unset(array('note1', 'note2'));

		$user2 = DB::collection('users')->find($id2);
		$this->assertFalse(isset($user2['note1']));
		$this->assertFalse(isset($user2['note2']));
	}

	public function testUpdateSubdocument()
	{
		DB::collection('users')->insertGetId(array(
			'name' => 'John Doe',
			'address' => array('country' => 'Belgium')
		));

		DB::collection('users')->where('name', 'John Doe')->update(array('address.country' => 'England'));

		$check = DB::collection('users')->where('name', 'John Doe')->first();

		$this->assertEquals('England', $check['address']['country']);
	}

}
