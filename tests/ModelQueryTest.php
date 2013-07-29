<?php
require_once('vendor/autoload.php');
require_once('models/User.php');
require_once('tests/app.php');

use Jenssegers\Mongodb\Connection;
use Jenssegers\Mongodb\Model;
use Jenssegers\Mongodb\DatabaseManager;

class ModelQueryTest extends PHPUnit_Framework_TestCase {

	public function setUp()
	{
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

		$users = User::where('age', '=', 35)->get();
		$this->assertEquals(3, count($users));

		$users = User::where('age', '>=', 35)->get();
		$this->assertEquals(4, count($users));

		$users = User::where('age', '<=', 18)->get();
		$this->assertEquals(1, count($users));

		$users = User::where('age', '!=', 35)->get();
		$this->assertEquals(6, count($users));

		$users = User::where('age', '<>', 35)->get();
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

		$user = User::select('name', 'title')->first();

		$this->assertEquals('John Doe', $user->name);
		$this->assertEquals('admin', $user->title);
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

		$users = User::whereNotIn('age', array(33, 35))->get();
		$this->assertEquals(4, count($users));

		$users = User::whereNotNull('age')
		             ->whereNotIn('age', array(33, 35))->get();
		$this->assertEquals(3, count($users));
	}

	public function testWhereNull()
	{
		$users = User::whereNull('age')->get();
		$this->assertEquals(1, count($users));
	}

	public function testWhereNotNull()
	{
		$users = User::whereNotNull('age')->get();
		$this->assertEquals(8, count($users));
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

	public function testIncrements()
	{
		User::where('name', 'John Doe')->increment('age');
		User::where('name', 'John Doe')->increment('age', 2, array('title' => 'user'));

		$user = User::where('name', 'John Doe')->first();
		$this->assertEquals(38, $user->age);
		$this->assertEquals('user', $user->title);

		User::where('name', 'John Doe')->decrement('age');
		$num = User::where('name', 'John Doe')->decrement('age', 2, array('title' => 'admin'));

		$user = User::where('name', 'John Doe')->first();
		$this->assertEquals(35, $user->age);
		$this->assertEquals('admin', $user->title);
		$this->assertEquals(1, $num);

		User::increment('age');
		User::increment('age', 2);

		$user = User::where('name', 'Mark Moe')->first();
		$this->assertEquals(26, $user->age);

		User::decrement('age', 2);
		$num = User::decrement('age');

		$user = User::where('name', 'Mark Moe')->first();
		$this->assertEquals(23, $user->age);
		$this->assertEquals(8, $num);
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
	
		$this->assertEquals(33, User::where('title', 'admin')->min('age'));
		$this->assertEquals(13, User::where('title', 'user')->min('age'));
	}

	public function testGroupBy()
	{
		$users = User::groupBy('title')->get();
		$this->assertEquals(3, count($users));

		$users = User::groupBy('age')->get();
		$this->assertEquals(6, count($users));

		$users = User::groupBy('age')->orderBy('age', 'desc')->get();
		$this->assertEquals(37, $users[0]->age);
		$this->assertEquals(35, $users[1]->age);
		$this->assertEquals(33, $users[2]->age);
	}

	public function testSubquery()
	{
		$users = User::where('title', 'admin')->orWhere(function($query)
            {
                $query->where('name', 'Tommy Toe')
                      ->orWhere('name', 'Error');
            })
            ->get();

        $this->assertEquals(5, count($users));

        $users = User::where('title', 'user')->where(function($query)
            {
                $query->where('age', 35)
                      ->orWhere('name', 'like', '%harry%');
            })
            ->get();

        $this->assertEquals(2, count($users));

        $users = User::where('age', 35)->orWhere(function($query)
            {
                $query->where('title', 'admin')
                      ->orWhere('name', 'Error');
            })
            ->get();

        $this->assertEquals(5, count($users));
	}

	public function testUpdate()
	{
		User::where('name', 'John Doe')
            ->update(array('age' => 100));

        $user = User::where('name', 'John Doe')->first();
		$this->assertEquals(100, $user->age);
	}

	public function testDelete()
	{
		User::where('age', '>', 30)->delete();
		$this->assertEquals(3, User::count());
	}

	public function testInsert()
	{
		User::insert(
		    array('name' => 'Francois', 'age' => 59, 'title' => 'Senior')
		);

		$this->assertEquals(10, User::count());

		User::insert(array(
		    array('name' => 'Gaston', 'age' => 60, 'title' => 'Senior'),
		    array('name' => 'Jaques', 'age' => 61, 'title' => 'Senior')
		));

		$this->assertEquals(12, User::count());
	}

	public function testInsertGetId()
	{
		$id = User::insertGetId(
		    array('name' => 'Gaston', 'age' => 60, 'title' => 'Senior')
		);

		$this->assertEquals(10, User::count());
		$this->assertNotNull($id);
		$this->assertTrue(is_string($id));
	}

	public function testRaw()
	{
		$where = array('age' => array('$gt' => 30, '$lt' => 40));
		$users = User::whereRaw($where)->get();

		$this->assertEquals(6, count($users));

		$where1 = array('age' => array('$gt' => 30, '$lte' => 35));
		$where2 = array('age' => array('$gt' => 35, '$lt' => 40));
		$users = User::whereRaw($where1)->orWhereRaw($where2)->get();

		$this->assertEquals(6, count($users));
	}

}