<?php
require_once('models/User.php');

use Jenssegers\Mongodb\Connection;
use Jenssegers\Mongodb\Model;
use Jenssegers\Mongodb\ConnectionResolver;

class QueryTest extends PHPUnit_Framework_TestCase {

	public function setUp()
	{
		$app = array();
		$app['config']['database']['connections']['mongodb'] = array(
			'host'     => 'localhost',
			'database' => 'unittest'
		);

		Model::setConnectionResolver(new ConnectionResolver($app));

		// test data
		User::create(array('name' => 'John Doe', 'age' => 35, 'title' => 'admin'));
		User::create(array('name' => 'Jane Doe', 'age' => 33, 'title' => 'admin'));
		User::create(array('name' => 'Harry Hoe', 'age' => 13, 'title' => 'user'));
		User::create(array('name' => 'Robert Roe', 'age' => 37, 'title' => 'user'));
		User::create(array('name' => 'Mark Moe', 'age' => 23, 'title' => 'user'));
		User::create(array('name' => 'Brett Boe', 'age' => 35, 'title' => 'user'));
		User::create(array('name' => 'Tommy Toe', 'age' => 33, 'title' => 'user'));
		User::create(array('name' => 'Yvonne Yoe', 'age' => 35, 'title' => 'admin'));
		User::create(array('name' => 'Error', 'age' => null, 'title' => null));
	}

	public function tearDown()
	{
		User::truncate();
	}

	public function testGet()
	{
		$users = User::get();
		$this->assertEquals(9, count($users));
		$this->assertInstanceOf('Jenssegers\Mongodb\Model', $users[0]);
	}

	public function testFirst()
	{
		$user = User::get()->first();
		$this->assertInstanceOf('Jenssegers\Mongodb\Model', $user);
		$this->assertEquals('John Doe', $user->name);
	}

	public function testWhere()
	{
		$users = User::where('age', 35)->get();
		$this->assertEquals(3, count($users));

		$users = User::where('age', '>=', 35)->get();
		$this->assertEquals(4, count($users));

		$users = User::where('age', '<=', 18)->get();
		$this->assertEquals(1, count($users));

		$users = User::where('age', '!=', 35)->get();
		$this->assertEquals(6, count($users));
	}

	public function testAndWhere()
	{
		$users = User::where('age', 35)->where('title', 'admin')->get();
		$this->assertEquals(2, count($users));

		$users = User::where('age', '>=', 35)->where('title', 'user')->get();
		$this->assertEquals(2, count($users));
	}

	public function testLike()
	{
		$users = User::where('name', 'like', '%doe')->get();
		$this->assertEquals(2, count($users));

		$users = User::where('name', 'like', '%y%')->get();
		$this->assertEquals(3, count($users));

		$users = User::where('name', 'like', 't%')->get();
		$this->assertEquals(1, count($users));
	}

	public function testPluck()
	{
		$name = User::where('name', 'John Doe')->pluck('name');
		$this->assertEquals('John Doe', $name);
	}

	public function testList()
	{
		$list = User::lists('title');
		$this->assertEquals(9, count($list));
		$this->assertEquals('admin', $list[0]);

		$list = User::lists('title', 'name');
		$this->assertEquals(9, count($list));
		$this->assertEquals('John Doe', key($list));
		$this->assertEquals('admin', $list['John Doe']);
	}

	public function testSelect()
	{
		$user = User::select('name')->first();

		$this->assertEquals('John Doe', $user->name);
		$this->assertEquals(null, $user->age);

		$user = User::get(array('name'))->first();

		$this->assertEquals('John Doe', $user->name);
		$this->assertEquals(null, $user->age);
	}

	public function testOrWhere()
	{
		$users = User::where('age', 13)->orWhere('title', 'admin')->get();
		$this->assertEquals(4, count($users));

		$users = User::where('age', 13)->orWhere('age', 23)->get();
		$this->assertEquals(2, count($users));
	}

	public function testBetween()
	{
		$users = User::whereBetween('age', array(0, 25))->get();
		$this->assertEquals(2, count($users));

		$users = User::whereBetween('age', array(13, 23))->get();
		$this->assertEquals(2, count($users));
	}

	public function testIn()
	{
		$users = User::whereIn('age', array(13, 23))->get();
		$this->assertEquals(2, count($users));

		$users = User::whereIn('age', array(33, 35, 13))->get();
		$this->assertEquals(6, count($users));
	}

	public function testWhereNull()
	{
		$users = User::whereNull('age')->get();
		$this->assertEquals(1, count($users));
	}

	public function testOrder()
	{
		$user = User::whereNotNull('age')->orderBy('age', 'asc')->first();
		$this->assertEquals(13, $user->age);

		$user = User::whereNotNull('age')->orderBy('age', 'desc')->first();
		$this->assertEquals(37, $user->age);
	}

	public function testTake()
	{
		$users = User::take(3)->get();
		$this->assertEquals(3, count($users));
	}

	public function testOffset()
	{
		$users = User::skip(1)->take(2)->get();
		$this->assertEquals(2, count($users));
		$this->assertEquals('Jane Doe', $users[0]->name);
	}

	public function testAggregates()
	{
		$this->assertEquals(9, User::count());
		$this->assertEquals(37, User::max('age'));
		$this->assertEquals(13, User::min('age'));
		$this->assertEquals(30.5, User::avg('age'));
		$this->assertEquals(244, User::sum('age'));

		$this->assertEquals(35, User::where('title', 'admin')->max('age'));
		$this->assertEquals(37, User::where('title', 'user')->max('age'));
	}

}