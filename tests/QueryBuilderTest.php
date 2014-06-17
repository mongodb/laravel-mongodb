<?php

class QueryBuilderTest extends TestCase {

	public function tearDown()
	{
		DB::collection('users')->truncate();
		DB::collection('items')->truncate();
	}

	public function testCollection()
	{
		$this->assertInstanceOf('Jenssegers\Mongodb\Query\Builder', DB::collection('users'));
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
		$this->assertInstanceOf('MongoId', $id);
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
		$this->assertTrue(is_array($users[0]['tags']));
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

		$john = DB::collection('users')->where('name', 'John Doe')->first();
		$jane = DB::collection('users')->where('name', 'Jane Doe')->first();
		$this->assertEquals(100, $john['age']);
		$this->assertEquals(20, $jane['age']);
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

		$cursor = DB::collection('users')->raw(function($collection)
		{
			return $collection->find(array('age' => 20));
		});

		$this->assertInstanceOf('MongoCursor', $cursor);
		$this->assertEquals(1, $cursor->count());

		$collection = DB::collection('users')->raw();
		$this->assertInstanceOf('Jenssegers\Mongodb\Collection', $collection);

		$collection = User::raw();
		$this->assertInstanceOf('Jenssegers\Mongodb\Collection', $collection);

		$results = DB::collection('users')->whereRaw(array('age' => 20))->get();
		$this->assertEquals(1, count($results));
		$this->assertEquals('Jane Doe', $results[0]['name']);
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
		$this->assertEquals(2, count($user['tags']));
		$this->assertEquals('tag2', $user['tags'][1]);

		// Add duplicate
		DB::collection('users')->where('_id', $id)->push('tags', 'tag2');
		$user = DB::collection('users')->find($id);
		$this->assertEquals(3, count($user['tags']));

		// Add unique
		DB::collection('users')->where('_id', $id)->push('tags', 'tag1', true);
		$user = DB::collection('users')->find($id);
		$this->assertEquals(3, count($user['tags']));

		$message = array('from' => 'Jane', 'body' => 'Hi John');
		DB::collection('users')->where('_id', $id)->push('messages', $message);
		$user = DB::collection('users')->find($id);
		$this->assertTrue(is_array($user['messages']));
		$this->assertEquals(1, count($user['messages']));
		$this->assertEquals($message, $user['messages'][0]);

		// Raw
		DB::collection('users')->where('_id', $id)->push(array('tags' => 'tag3', 'messages' => array('from' => 'Mark', 'body' => 'Hi John')));
		$user = DB::collection('users')->find($id);
		$this->assertEquals(4, count($user['tags']));
		$this->assertEquals(2, count($user['messages']));
	}

	public function testPull()
	{
		$message1 = array('from' => 'Jane', 'body' => 'Hi John');
		$message2 = array('from' => 'Mark', 'body' => 'Hi John');

		$id = DB::collection('users')->insertGetId(array(
			'name' => 'John Doe',
			'tags' => array('tag1', 'tag2', 'tag3', 'tag4'),
			'messages' => array($message1, $message2)
		));

		DB::collection('users')->where('_id', $id)->pull('tags', 'tag3');

		$user = DB::collection('users')->find($id);
		$this->assertTrue(is_array($user['tags']));
		$this->assertEquals(3, count($user['tags']));
		$this->assertEquals('tag4', $user['tags'][2]);

		DB::collection('users')->where('_id', $id)->pull('messages', $message1);

		$user = DB::collection('users')->find($id);
		$this->assertTrue(is_array($user['messages']));
		$this->assertEquals(1, count($user['messages']));

		// Raw
		DB::collection('users')->where('_id', $id)->pull(array('tags' => 'tag2', 'messages' => $message2));
		$user = DB::collection('users')->find($id);
		$this->assertEquals(2, count($user['tags']));
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

		$items = DB::collection('items')->distinct('name')->get(); sort($items);
		$this->assertEquals(3, count($items));
		$this->assertEquals(array('fork', 'knife', 'spoon'), $items);

		$types = DB::collection('items')->distinct('type')->get(); sort($types);
		$this->assertEquals(2, count($types));
		$this->assertEquals(array('round', 'sharp'), $types);
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

		$items = DB::collection('items')->orderBy('name')->take(2)->get();
		$this->assertEquals(2, count($items));
		$this->assertEquals('fork', $items[0]['name']);
	}

	public function testSkip()
	{
		DB::collection('items')->insert(array(
			array('name' => 'knife', 'type' => 'sharp', 'amount' => 34),
			array('name' => 'fork',  'type' => 'sharp', 'amount' => 20),
			array('name' => 'spoon', 'type' => 'round', 'amount' => 3),
			array('name' => 'spoon', 'type' => 'round', 'amount' => 14)
		));

		$items = DB::collection('items')->orderBy('name')->skip(2)->get();
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
		sort($list);
		$this->assertEquals(4, count($list));
		$this->assertEquals(array('fork', 'knife', 'spoon', 'spoon'), $list);

		$list = DB::collection('items')->lists('type', 'name');
		$this->assertEquals(3, count($list));
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
		$id = DB::collection('users')->insertGetId(array('name' => 'John Doe', 'address' => array('country' => 'Belgium')));

		DB::collection('users')->where('_id', $id)->update(array('address.country' => 'England'));

		$check = DB::collection('users')->find($id);
		$this->assertEquals('England', $check['address']['country']);
	}

	public function testDates()
	{
		DB::collection('users')->insert(array(
			array('name' => 'John Doe', 'birthday' => new MongoDate(strtotime("1980-01-01 00:00:00"))),
			array('name' => 'Jane Doe', 'birthday' => new MongoDate(strtotime("1981-01-01 00:00:00"))),
			array('name' => 'Robert Roe', 'birthday' => new MongoDate(strtotime("1982-01-01 00:00:00"))),
			array('name' => 'Mark Moe', 'birthday' => new MongoDate(strtotime("1983-01-01 00:00:00"))),
		));

		$user = DB::collection('users')->where('birthday', new MongoDate(strtotime("1980-01-01 00:00:00")))->first();
		$this->assertEquals('John Doe', $user['name']);

		$user = DB::collection('users')->where('birthday', '=', new DateTime("1980-01-01 00:00:00"))->first();
		$this->assertEquals('John Doe', $user['name']);

		$start = new MongoDate(strtotime("1981-01-01 00:00:00"));
		$stop = new MongoDate(strtotime("1982-01-01 00:00:00"));

		$users = DB::collection('users')->whereBetween('birthday', array($start, $stop))->get();
		$this->assertEquals(2, count($users));
	}

	public function testOperators()
	{
		DB::collection('users')->insert(array(
			array('name' => 'John Doe', 'age' => 30),
			array('name' => 'Jane Doe'),
			array('name' => 'Robert Roe', 'age' => 'thirty-one'),
		));

		$results = DB::collection('users')->where('age', 'exists', true)->get();
		$this->assertEquals(2, count($results));
		$resultsNames = array($results[0]['name'], $results[1]['name']);
		$this->assertContains('John Doe', $resultsNames);
		$this->assertContains('Robert Roe', $resultsNames);

		$results = DB::collection('users')->where('age', 'exists', false)->get();
		$this->assertEquals(1, count($results));
		$this->assertEquals('Jane Doe', $results[0]['name']);

		$results = DB::collection('users')->where('age', 'type', 2)->get();
		$this->assertEquals(1, count($results));
		$this->assertEquals('Robert Roe', $results[0]['name']);

		$results = DB::collection('users')->where('age', 'mod', array(15, 0))->get();
		$this->assertEquals(1, count($results));
		$this->assertEquals('John Doe', $results[0]['name']);

		$results = DB::collection('users')->where('age', 'mod', array(29, 1))->get();
		$this->assertEquals(1, count($results));
		$this->assertEquals('John Doe', $results[0]['name']);

		$results = DB::collection('users')->where('age', 'mod', array(14, 0))->get();
		$this->assertEquals(0, count($results));

		DB::collection('items')->insert(array(
			array('name' => 'fork',  'tags' => array('sharp', 'pointy')),
			array('name' => 'spork', 'tags' => array('sharp', 'pointy', 'round', 'bowl')),
			array('name' => 'spoon', 'tags' => array('round', 'bowl')),
		));

		$results = DB::collection('items')->where('tags', 'all', array('sharp', 'pointy'))->get();
		$this->assertEquals(2, count($results));

		$results = DB::collection('items')->where('tags', 'all', array('sharp', 'round'))->get();
		$this->assertEquals(1, count($results));

		$results = DB::collection('items')->where('tags', 'size', 2)->get();
		$this->assertEquals(2, count($results));

		$results = DB::collection('items')->where('tags', '$size', 2)->get();
		$this->assertEquals(2, count($results));

		$results = DB::collection('items')->where('tags', 'size', 3)->get();
		$this->assertEquals(0, count($results));

		$results = DB::collection('items')->where('tags', 'size', 4)->get();
		$this->assertEquals(1, count($results));

		$regex = new MongoRegex("/.*doe/i");
		$results = DB::collection('users')->where('name', 'regex', $regex)->get();
		$this->assertEquals(2, count($results));

		$regex = new MongoRegex("/.*doe/i");
		$results = DB::collection('users')->where('name', 'regexp', $regex)->get();
		$this->assertEquals(2, count($results));

		$results = DB::collection('users')->where('name', 'REGEX', $regex)->get();
		$this->assertEquals(2, count($results));

		DB::collection('users')->insert(array(
			array(
				'name' => 'John Doe',
				'addresses' => array(
					array('city' => 'Ghent'),
					array('city' => 'Paris')
				)
			),
			array(
				'name' => 'Jane Doe',
				'addresses' => array(
					array('city' => 'Brussels'),
					array('city' => 'Paris')
				)
			)
		));

		$users = DB::collection('users')->where('addresses', 'elemMatch', array('city' => 'Brussels'))->get();
		$this->assertEquals(1, count($users));
		$this->assertEquals('Jane Doe', $users[0]['name']);
	}

	public function testIncrement()
	{
		DB::collection('users')->insert(array(
			array('name' => 'John Doe', 'age' => 30, 'note' => 'adult'),
			array('name' => 'Jane Doe', 'age' => 10, 'note' => 'minor'),
			array('name' => 'Robert Roe', 'age' => null),
			array('name' => 'Mark Moe'),
		));

		$user = DB::collection('users')->where('name', 'John Doe')->first();
		$this->assertEquals(30, $user['age']);

		DB::collection('users')->where('name', 'John Doe')->increment('age');
		$user = DB::collection('users')->where('name', 'John Doe')->first();
		$this->assertEquals(31, $user['age']);

		DB::collection('users')->where('name', 'John Doe')->decrement('age');
		$user = DB::collection('users')->where('name', 'John Doe')->first();
		$this->assertEquals(30, $user['age']);

		DB::collection('users')->where('name', 'John Doe')->increment('age', 5);
		$user = DB::collection('users')->where('name', 'John Doe')->first();
		$this->assertEquals(35, $user['age']);

		DB::collection('users')->where('name', 'John Doe')->decrement('age', 5);
		$user = DB::collection('users')->where('name', 'John Doe')->first();
		$this->assertEquals(30, $user['age']);

		DB::collection('users')->where('name', 'Jane Doe')->increment('age', 10, array('note' => 'adult'));
		$user = DB::collection('users')->where('name', 'Jane Doe')->first();
		$this->assertEquals(20, $user['age']);
		$this->assertEquals('adult', $user['note']);

		DB::collection('users')->where('name', 'John Doe')->decrement('age', 20, array('note' => 'minor'));
		$user = DB::collection('users')->where('name', 'John Doe')->first();
		$this->assertEquals(10, $user['age']);
		$this->assertEquals('minor', $user['note']);

		DB::collection('users')->increment('age');
		$user = DB::collection('users')->where('name', 'John Doe')->first();
		$this->assertEquals(11, $user['age']);
		$user = DB::collection('users')->where('name', 'Jane Doe')->first();
		$this->assertEquals(21, $user['age']);
		$user = DB::collection('users')->where('name', 'Robert Roe')->first();
		$this->assertEquals(null, $user['age']);
		$user = DB::collection('users')->where('name', 'Mark Moe')->first();
		$this->assertEquals(1, $user['age']);
	}

}
