<?php

class QueryTest extends TestCase {

	protected static $started = false;

	public function setUp()
	{
		parent::setUp();

		// only run this stuff once
		if (self::$started) return;

		User::create(array('name' => 'John Doe', 'age' => 35, 'title' => 'admin'));
		User::create(array('name' => 'Jane Doe', 'age' => 33, 'title' => 'admin'));
		User::create(array('name' => 'Harry Hoe', 'age' => 13, 'title' => 'user'));
		User::create(array('name' => 'Robert Roe', 'age' => 37, 'title' => 'user'));
		User::create(array('name' => 'Mark Moe', 'age' => 23, 'title' => 'user'));
		User::create(array('name' => 'Brett Boe', 'age' => 35, 'title' => 'user'));
		User::create(array('name' => 'Tommy Toe', 'age' => 33, 'title' => 'user'));
		User::create(array('name' => 'Yvonne Yoe', 'age' => 35, 'title' => 'admin'));
		User::create(array('name' => 'Error', 'age' => null, 'title' => null));

		self::$started = true;
	}

	public static function tearDownAfterClass()
	{
		User::truncate();
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

		$users = User::where('name', 'LIKE', '%y%')->get();
		$this->assertEquals(3, count($users));

		$users = User::where('name', 'like', 't%')->get();
		$this->assertEquals(1, count($users));
	}

	public function testSelect()
	{
		$user = User::where('name', 'John Doe')->select('name')->first();

		$this->assertEquals('John Doe', $user->name);
		$this->assertEquals(null, $user->age);
		$this->assertEquals(null, $user->title);

		$user = User::where('name', 'John Doe')->select('name', 'title')->first();

		$this->assertEquals('John Doe', $user->name);
		$this->assertEquals('admin', $user->title);
		$this->assertEquals(null, $user->age);

		$user = User::where('name', 'John Doe')->select(array('name', 'title'))->get()->first();

		$this->assertEquals('John Doe', $user->name);
		$this->assertEquals('admin', $user->title);
		$this->assertEquals(null, $user->age);

		$user = User::where('name', 'John Doe')->get(array('name'))->first();

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

		// testing whereNotBetween for version 4.1
		$users = User::whereBetween('age', array(0, 25), 'and', true)->get();
		$this->assertEquals(6, count($users));
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

		$user = User::whereNotNull('age')->orderBy('age', 'ASC')->first();
		$this->assertEquals(13, $user->age);

		$user = User::whereNotNull('age')->orderBy('age', 'desc')->first();
		$this->assertEquals(37, $user->age);

		$user = User::whereNotNull('age')->orderBy('natural', 'asc')->first();
		$this->assertEquals(35, $user->age);

		$user = User::whereNotNull('age')->orderBy('natural', 'ASC')->first();
		$this->assertEquals(35, $user->age);

		$user = User::whereNotNull('age')->orderBy('natural', 'desc')->first();
		$this->assertEquals(35, $user->age);
	}

	public function testGroupBy()
	{
		$users = User::groupBy('title')->get();
		$this->assertEquals(3, count($users));

		$users = User::groupBy('age')->get();
		$this->assertEquals(6, count($users));

		$users = User::groupBy('age')->skip(1)->get();
		$this->assertEquals(5, count($users));

		$users = User::groupBy('age')->take(2)->get();
		$this->assertEquals(2, count($users));

		$users = User::groupBy('age')->orderBy('age', 'desc')->get();
		$this->assertEquals(37, $users[0]->age);
		$this->assertEquals(35, $users[1]->age);
		$this->assertEquals(33, $users[2]->age);

		$users = User::groupBy('age')->skip(1)->take(2)->orderBy('age', 'desc')->get();
		$this->assertEquals(2, count($users));
		$this->assertEquals(35, $users[0]->age);
		$this->assertEquals(33, $users[1]->age);
		$this->assertNull($users[0]->name);

		$users = User::select('name')->groupBy('age')->skip(1)->take(2)->orderBy('age', 'desc')->get();
		$this->assertEquals(2, count($users));
		$this->assertNotNull($users[0]->name);
	}

	public function testCount()
	{
		$count = User::where('age', '<>', 35)->count();
		$this->assertEquals(6, $count);

		// Test for issue #165
		$count = User::select('_id', 'age', 'title')->where('age', '<>', 35)->count();
		$this->assertEquals(6, $count);
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

	public function testWhereRaw()
	{
		$where = array('age' => array('$gt' => 30, '$lt' => 40));
		$users = User::whereRaw($where)->get();

		$this->assertEquals(6, count($users));

		$where1 = array('age' => array('$gt' => 30, '$lte' => 35));
		$where2 = array('age' => array('$gt' => 35, '$lt' => 40));
		$users = User::whereRaw($where1)->orWhereRaw($where2)->get();

		$this->assertEquals(6, count($users));
	}

	public function testMultipleOr()
	{
		$users = User::where(function($query)
		{
			$query->where('age', 35)->orWhere('age', 33);
		})
		->where(function($query)
		{
			$query->where('name', 'John Doe')->orWhere('name', 'Jane Doe');
		})->get();

		$this->assertEquals(2, count($users));

		$users = User::where(function($query)
		{
			$query->orWhere('age', 35)->orWhere('age', 33);
		})
		->where(function($query)
		{
			$query->orWhere('name', 'John Doe')->orWhere('name', 'Jane Doe');
		})->get();

		$this->assertEquals(2, count($users));
	}

}
