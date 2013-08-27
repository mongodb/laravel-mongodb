<?php
require_once('tests/app.php');

class QueryTest extends PHPUnit_Framework_TestCase {

	public static function setUpBeforeClass()
	{
		User::create(array('name' => 'John Doe', 'age' => 35, 'title' => 'admin', 'subdocument' => array('age' => 35)));
		User::create(array('name' => 'Jane Doe', 'age' => 33, 'title' => 'admin', 'subdocument' => array('age' => 33)));
		User::create(array('name' => 'Harry Hoe', 'age' => 13, 'title' => 'user', 'subdocument' => array('age' => 13)));
		User::create(array('name' => 'Robert Roe', 'age' => 37, 'title' => 'user', 'subdocument' => array('age' => 37)));
		User::create(array('name' => 'Mark Moe', 'age' => 23, 'title' => 'user', 'subdocument' => array('age' => 23)));
		User::create(array('name' => 'Brett Boe', 'age' => 35, 'title' => 'user', 'subdocument' => array('age' => 35)));
		User::create(array('name' => 'Tommy Toe', 'age' => 33, 'title' => 'user', 'subdocument' => array('age' => 33)));
		User::create(array('name' => 'Yvonne Yoe', 'age' => 35, 'title' => 'admin', 'subdocument' => array('age' => 35)));
		User::create(array('name' => 'Error', 'age' => null, 'title' => null));
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

		$users = User::where('name', 'like', 't%')->get();
		$this->assertEquals(1, count($users));
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

		$this->assertEquals(37, User::max('subdocument.age'));
		$this->assertEquals(13, User::min('subdocument.age'));
		$this->assertEquals(30.5, User::avg('subdocument.age'));
		$this->assertEquals(244, User::sum('subdocument.age'));

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

		$users = User::groupBy('age')->skip(1)->get();
		$this->assertEquals(5, count($users));

		$users = User::groupBy('age')->take(2)->get();
		$this->assertEquals(2, count($users));

		$users = User::groupBy('age')->orderBy('age', 'desc')->get();
		$this->assertEquals(37, $users[0]->age);
		$this->assertEquals(35, $users[1]->age);
		$this->assertEquals(33, $users[2]->age);

		$users = User::groupBy('age')->skip(1)->take(2)->orderBy('age', 'desc')->get();
		$this->assertEquals(35, $users[0]->age);
		$this->assertEquals(33, $users[1]->age);
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