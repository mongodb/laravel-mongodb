<?php

use Carbon;

class ModelTest extends PHPUnit_Framework_TestCase {

	public function setUp() {}

	public function tearDown()
	{
		User::truncate();
		Soft::truncate();
		Book::truncate();
		Item::truncate();
	}

	public function testNewModel()
	{
		$user = new User;
		$this->assertInstanceOf('Jenssegers\Mongodb\Model', $user);
		$this->assertEquals(false, $user->exists);
		$this->assertEquals('users', $user->getTable());
		$this->assertEquals('_id', $user->getKeyName());
		$this->assertEquals('users._id', $user->getQualifiedKeyName());
	}

	public function testInsert()
	{
		$user = new User;
		$user->name = 'John Doe';
		$user->title = 'admin';
		$user->age = 35;

		$user->save();

		$this->assertEquals(true, $user->exists);
		$this->assertEquals(1, User::count());

		$this->assertTrue(isset($user->_id));
		$this->assertNotEquals('', (string) $user->_id);
		$this->assertNotEquals(0, strlen((string) $user->_id));
		$this->assertInstanceOf('Carbon\Carbon', $user->created_at);

		$this->assertEquals('John Doe', $user->name);
		$this->assertEquals(35, $user->age);
	}

	public function testUpdate()
	{
		$user = new User;
		$user->name = 'John Doe';
		$user->title = 'admin';
		$user->age = 35;
		$user->save();

		$check = User::find($user->_id);

		$check->age = 36;
		$check->save();

		$this->assertEquals(true, $check->exists);
		$this->assertInstanceOf('Carbon\Carbon', $check->created_at);
		$this->assertInstanceOf('Carbon\Carbon', $check->updated_at);
		$this->assertEquals(1, User::count());

		$this->assertEquals('John Doe', $check->name);
		$this->assertEquals(36, $check->age);

		$user->update(array('age' => 20));

		$check = User::find($user->_id);
		$this->assertEquals(20, $check->age);
	}

	public function testDelete()
	{
		$user = new User;
		$user->name = 'John Doe';
		$user->title = 'admin';
		$user->age = 35;
		$user->save();

		$this->assertEquals(true, $user->exists);
		$this->assertEquals(1, User::count());

		$user->delete();

		$this->assertEquals(0, User::count());
	}

	public function testAll()
	{
		$user = new User;
		$user->name = 'John Doe';
		$user->title = 'admin';
		$user->age = 35;
		$user->save();

		$user = new User;
		$user->name = 'Jane Doe';
		$user->title = 'user';
		$user->age = 32;
		$user->save();

		$all = User::all();

		$this->assertEquals(2, count($all));
		$this->assertEquals('John Doe', $all[0]->name);
		$this->assertEquals('Jane Doe', $all[1]->name);
	}

	public function testFind()
	{
		$user = new User;
		$user->name = 'John Doe';
		$user->title = 'admin';
		$user->age = 35;
		$user->save();

		$check = User::find($user->_id);

		$this->assertInstanceOf('Jenssegers\Mongodb\Model', $check);
		$this->assertEquals(true, $check->exists);
		$this->assertEquals($user->_id, $check->_id);

		$this->assertEquals('John Doe', $check->name);
		$this->assertEquals(35, $check->age);
	}

	public function testGet()
	{
		User::insert(array(
			array('name' => 'John Doe'),
			array('name' => 'Jane Doe')
		));

		$users = User::get();
		$this->assertEquals(2, count($users));
		$this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $users);
		$this->assertInstanceOf('Jenssegers\Mongodb\Model', $users[0]);
	}

	public function testFirst()
	{
		User::insert(array(
			array('name' => 'John Doe'),
			array('name' => 'Jane Doe')
		));

		$user = User::get()->first();
		$this->assertInstanceOf('Jenssegers\Mongodb\Model', $user);
		$this->assertEquals('John Doe', $user->name);
	}

	public function testNoDocument()
	{
		$items = Item::where('name', 'nothing')->get();
		$this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $items);
		$this->assertEquals(0, $items->count());

		$item =Item::where('name', 'nothing')->first();
		$this->assertEquals(null, $item);

		$item = Item::find('51c33d8981fec6813e00000a');
		$this->assertEquals(null, $item);
	}

	/**
     * @expectedException Illuminate\Database\Eloquent\ModelNotFoundException
     */
	public function testFindOrfail()
	{
		User::findOrfail('51c33d8981fec6813e00000a');
	}

	public function testCreate()
	{
		$user = User::create(array('name' => 'Jane Poe'));

		$this->assertInstanceOf('Jenssegers\Mongodb\Model', $user);
		$this->assertEquals(true, $user->exists);
		$this->assertEquals('Jane Poe', $user->name);
	}

	public function testDestroy()
	{
		$user = new User;
		$user->name = 'John Doe';
		$user->title = 'admin';
		$user->age = 35;
		$user->save();

		User::destroy((string) $user->_id);

		$this->assertEquals(0, User::count());
	}

	public function testTouch()
	{
		$user = new User;
		$user->name = 'John Doe';
		$user->title = 'admin';
		$user->age = 35;
		$user->save();

		$old = $user->updated_at;

		sleep(1);
		$user->touch();
		$check = User::find($user->_id);

		$this->assertNotEquals($old, $check->updated_at);
	}

	public function testSoftDelete()
	{
		$user = new Soft;
		$user->name = 'Softy';
		$user->save();
		$this->assertEquals(true, $user->exists);

		$user->delete();

		$check = Soft::find($user->_id);
		$this->assertEquals(null, $check);

		$all = Soft::get();
		$this->assertEquals(0, $all->count());

		$all = Soft::withTrashed()->get();
		$this->assertEquals(1, $all->count());

		$check = $all[0];
		$this->assertInstanceOf('Carbon\Carbon', $check->deleted_at);
		$this->assertEquals(true, $check->trashed());

		$check->restore();
		$all = Soft::get();
		$this->assertEquals(1, $all->count());
	}

	public function testPrimaryKey()
	{
		$user = new User;
		$this->assertEquals('_id', $user->getKeyName());

		$book = new Book;
		$this->assertEquals('title', $book->getKeyName());

		$book->title = 'A Game of Thrones';
		$book->author = 'George R. R. Martin';
		$book->save();

		$this->assertEquals('A Game of Thrones', $book->getKey());

		$check = Book::find('A Game of Thrones');
		$this->assertEquals('title', $check->getKeyName());
		$this->assertEquals('A Game of Thrones', $check->getKey());
		$this->assertEquals('A Game of Thrones', $check->title);
	}

	public function testScope()
	{
		Item::insert(array(
			array('name' => 'knife', 'type' => 'sharp'),
			array('name' => 'spoon', 'type' => 'round')
		));

		$sharp = Item::sharp()->get();
		$this->assertEquals(1, $sharp->count());
	}

	public function testToArray()
	{
		$original = array(
			array('name' => 'knife', 'type' => 'sharp'),
			array('name' => 'spoon', 'type' => 'round')
		);

		Item::insert($original);

		$items = Item::all();
		$this->assertEquals($original, $items->toArray());
		$this->assertEquals($original[0], $items[0]->toArray());
	}

	public function testUnset()
	{
		$user1 = User::create(array('name' => 'John Doe', 'note1' => 'ABC', 'note2' => 'DEF'));
		$user2 = User::create(array('name' => 'Jane Doe', 'note1' => 'ABC', 'note2' => 'DEF'));

		$user1->unset('note1');

		$this->assertFalse(isset($user1->note1));
		$this->assertTrue(isset($user1->note2));
		$this->assertTrue(isset($user2->note1));
		$this->assertTrue(isset($user2->note2));

		// Re-fetch to be sure
		$user1 = User::find($user1->_id);
		$user2 = User::find($user2->_id);

		$this->assertFalse(isset($user1->note1));
		$this->assertTrue(isset($user1->note2));
		$this->assertTrue(isset($user2->note1));
		$this->assertTrue(isset($user2->note2));

		$user2->unset(array('note1', 'note2'));

		$this->assertFalse(isset($user2->note1));
		$this->assertFalse(isset($user2->note2));
	}

	public function testDates()
	{
		$user = User::create(array('name' => 'John Doe', 'birthday' => new DateTime('1980/1/1')));
		$this->assertInstanceOf('Carbon\Carbon', $user->birthday);

		$check = User::find($user->_id);
		$this->assertInstanceOf('Carbon\Carbon', $check->birthday);
		$this->assertEquals($user->birthday, $check->birthday);

		$user = User::where('birthday', '>', new DateTime('1975/1/1'))->first();
		$this->assertEquals('John Doe', $user->name);
	}

}
