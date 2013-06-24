<?php
require_once('vendor/autoload.php');
require_once('models/User.php');
require_once('models/Soft.php');

use Jenssegers\Mongodb\Connection;
use Jenssegers\Mongodb\Model;
use Jenssegers\Mongodb\DatabaseManager;

class ModelTest extends PHPUnit_Framework_TestCase {

	public function setUp()
	{
		include('tests/app.php');
		Model::setConnectionResolver(new DatabaseManager($app));
	}

	public function tearDown()
	{
		User::truncate();
		Soft::truncate();
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
		$user->name = "John Doe";
		$user->title = "admin";
		$user->age = 35;

		$user->save();

		$this->assertEquals(true, $user->exists);
		$this->assertEquals(1, User::count());

		$this->assertTrue(isset($user->_id));
		$this->assertNotEquals('', (string) $user->_id);
		$this->assertNotEquals(0, strlen((string) $user->_id));
		$this->assertInstanceOf('DateTime', $user->created_at);

		$this->assertEquals("John Doe", $user->name);
		$this->assertEquals(35, $user->age);
	}

	public function testUpdate()
	{
		$user = new User;
		$user->name = "John Doe";
		$user->title = "admin";
		$user->age = 35;
		$user->save();

		$check = User::find($user->_id);

		$check->age = 36;
		$check->save();

		$this->assertEquals(true, $check->exists);
		$this->assertInstanceOf('DateTime', $check->created_at);
		$this->assertInstanceOf('DateTime', $check->updated_at);
		$this->assertEquals(1, User::count());

		$this->assertEquals("John Doe", $check->name);
		$this->assertEquals(36, $check->age);
	}

	public function testDelete()
	{
		$user = new User;
		$user->name = "John Doe";
		$user->title = "admin";
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
		$user->name = "John Doe";
		$user->title = "admin";
		$user->age = 35;
		$user->save();

		$user = new User;
		$user->name = "Jane Doe";
		$user->title = "user";
		$user->age = 32;
		$user->save();

		$all = User::all();

		$this->assertEquals(2, count($all));
		$this->assertEquals("John Doe", $all[0]->name);
		$this->assertEquals("Jane Doe", $all[1]->name);
	}

	public function testFind()
	{
		$user = new User;
		$user->name = "John Doe";
		$user->title = "admin";
		$user->age = 35;
		$user->save();

		$check = User::find($user->_id);

		$this->assertInstanceOf('Jenssegers\Mongodb\Model', $check);
		$this->assertEquals(true, $check->exists);
		$this->assertEquals($user->_id, $check->_id);

		$this->assertEquals("John Doe", $check->name);
		$this->assertEquals(35, $check->age);
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

		$this->assertEquals("Jane Poe", $user->name);
	}

	public function testDestroy()
	{
		$user = new User;
		$user->name = "John Doe";
		$user->title = "admin";
		$user->age = 35;
		$user->save();

		User::destroy((string) $user->_id);

		$this->assertEquals(0, User::count());
	}

	public function testTouch()
	{
		$user = new User;
		$user->name = "John Doe";
		$user->title = "admin";
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
		$user->name = "Softy";
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
		$this->assertInstanceOf('DateTime', $check->deleted_at);
		$this->assertEquals(true, $check->trashed());

		$check->restore();
		$all = Soft::get();
		$this->assertEquals(1, $all->count());
	}

}