<?php

class QueryTest extends TestCase
{
    protected static $started = false;

    public function setUp(): void
    {
        parent::setUp();
        User::create(['name' => 'John Doe', 'age' => 35, 'title' => 'admin']);
        User::create(['name' => 'Jane Doe', 'age' => 33, 'title' => 'admin']);
        User::create(['name' => 'Harry Hoe', 'age' => 13, 'title' => 'user']);
        User::create(['name' => 'Robert Roe', 'age' => 37, 'title' => 'user']);
        User::create(['name' => 'Mark Moe', 'age' => 23, 'title' => 'user']);
        User::create(['name' => 'Brett Boe', 'age' => 35, 'title' => 'user']);
        User::create(['name' => 'Tommy Toe', 'age' => 33, 'title' => 'user']);
        User::create(['name' => 'Yvonne Yoe', 'age' => 35, 'title' => 'admin']);
        User::create(['name' => 'Error', 'age' => null, 'title' => null]);
    }

    public function tearDown(): void
    {
        User::truncate();
        parent::tearDown();
    }

    public function testWhere()
    {
        $users = User::where('age', 35)->get();
        $this->assertCount(3, $users);

        $users = User::where('age', '=', 35)->get();
        $this->assertCount(3, $users);

        $users = User::where('age', '>=', 35)->get();
        $this->assertCount(4, $users);

        $users = User::where('age', '<=', 18)->get();
        $this->assertCount(1, $users);

        $users = User::where('age', '!=', 35)->get();
        $this->assertCount(6, $users);

        $users = User::where('age', '<>', 35)->get();
        $this->assertCount(6, $users);
    }

    public function testAndWhere()
    {
        $users = User::where('age', 35)->where('title', 'admin')->get();
        $this->assertCount(2, $users);

        $users = User::where('age', '>=', 35)->where('title', 'user')->get();
        $this->assertCount(2, $users);
    }

    public function testLike()
    {
        $users = User::where('name', 'like', '%doe')->get();
        $this->assertCount(2, $users);

        $users = User::where('name', 'like', '%y%')->get();
        $this->assertCount(3, $users);

        $users = User::where('name', 'LIKE', '%y%')->get();
        $this->assertCount(3, $users);

        $users = User::where('name', 'like', 't%')->get();
        $this->assertCount(1, $users);
    }

    public function testSelect()
    {
        $user = User::where('name', 'John Doe')->select('name')->first();

        $this->assertEquals('John Doe', $user->name);
        $this->assertNull($user->age);
        $this->assertNull($user->title);

        $user = User::where('name', 'John Doe')->select('name', 'title')->first();

        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('admin', $user->title);
        $this->assertNull($user->age);

        $user = User::where('name', 'John Doe')->select(['name', 'title'])->get()->first();

        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('admin', $user->title);
        $this->assertNull($user->age);

        $user = User::where('name', 'John Doe')->get(['name'])->first();

        $this->assertEquals('John Doe', $user->name);
        $this->assertNull($user->age);
    }

    public function testOrWhere()
    {
        $users = User::where('age', 13)->orWhere('title', 'admin')->get();
        $this->assertCount(4, $users);

        $users = User::where('age', 13)->orWhere('age', 23)->get();
        $this->assertCount(2, $users);
    }

    public function testBetween()
    {
        $users = User::whereBetween('age', [0, 25])->get();
        $this->assertCount(2, $users);

        $users = User::whereBetween('age', [13, 23])->get();
        $this->assertCount(2, $users);

        // testing whereNotBetween for version 4.1
        $users = User::whereBetween('age', [0, 25], 'and', true)->get();
        $this->assertCount(6, $users);
    }

    public function testIn()
    {
        $users = User::whereIn('age', [13, 23])->get();
        $this->assertCount(2, $users);

        $users = User::whereIn('age', [33, 35, 13])->get();
        $this->assertCount(6, $users);

        $users = User::whereNotIn('age', [33, 35])->get();
        $this->assertCount(4, $users);

        $users = User::whereNotNull('age')
                     ->whereNotIn('age', [33, 35])->get();
        $this->assertCount(3, $users);
    }

    public function testWhereNull()
    {
        $users = User::whereNull('age')->get();
        $this->assertCount(1, $users);
    }

    public function testWhereNotNull()
    {
        $users = User::whereNotNull('age')->get();
        $this->assertCount(8, $users);
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
        $this->assertCount(3, $users);

        $users = User::groupBy('age')->get();
        $this->assertCount(6, $users);

        $users = User::groupBy('age')->skip(1)->get();
        $this->assertCount(5, $users);

        $users = User::groupBy('age')->take(2)->get();
        $this->assertCount(2, $users);

        $users = User::groupBy('age')->orderBy('age', 'desc')->get();
        $this->assertEquals(37, $users[0]->age);
        $this->assertEquals(35, $users[1]->age);
        $this->assertEquals(33, $users[2]->age);

        $users = User::groupBy('age')->skip(1)->take(2)->orderBy('age', 'desc')->get();
        $this->assertCount(2, $users);
        $this->assertEquals(35, $users[0]->age);
        $this->assertEquals(33, $users[1]->age);
        $this->assertNull($users[0]->name);

        $users = User::select('name')->groupBy('age')->skip(1)->take(2)->orderBy('age', 'desc')->get();
        $this->assertCount(2, $users);
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

    public function testExists()
    {
        $this->assertFalse(User::where('age', '>', 37)->exists());
        $this->assertTrue(User::where('age', '<', 37)->exists());
    }

    public function testSubquery()
    {
        $users = User::where('title', 'admin')->orWhere(function ($query) {
            $query->where('name', 'Tommy Toe')
                      ->orWhere('name', 'Error');
        })
            ->get();

        $this->assertCount(5, $users);

        $users = User::where('title', 'user')->where(function ($query) {
            $query->where('age', 35)
                      ->orWhere('name', 'like', '%harry%');
        })
            ->get();

        $this->assertCount(2, $users);

        $users = User::where('age', 35)->orWhere(function ($query) {
            $query->where('title', 'admin')
                      ->orWhere('name', 'Error');
        })
            ->get();

        $this->assertCount(5, $users);

        $users = User::whereNull('deleted_at')
                ->where('title', 'admin')
                ->where(function ($query) {
                    $query->where('age', '>', 15)
                          ->orWhere('name', 'Harry Hoe');
                })
                ->get();

        $this->assertEquals(3, $users->count());

        $users = User::whereNull('deleted_at')
                ->where(function ($query) {
                    $query->where('name', 'Harry Hoe')
                          ->orWhere(function ($query) {
                              $query->where('age', '>', 15)
                                    ->where('title', '<>', 'admin');
                          });
                })
                ->get();

        $this->assertEquals(5, $users->count());
    }

    public function testWhereRaw()
    {
        $where = ['age' => ['$gt' => 30, '$lt' => 40]];
        $users = User::whereRaw($where)->get();

        $this->assertCount(6, $users);

        $where1 = ['age' => ['$gt' => 30, '$lte' => 35]];
        $where2 = ['age'           => ['$gt' => 35, '$lt' => 40]];
        $users = User::whereRaw($where1)->orWhereRaw($where2)->get();

        $this->assertCount(6, $users);
    }

    public function testMultipleOr()
    {
        $users = User::where(function ($query) {
            $query->where('age', 35)->orWhere('age', 33);
        })
        ->where(function ($query) {
            $query->where('name', 'John Doe')->orWhere('name', 'Jane Doe');
        })->get();

        $this->assertCount(2, $users);

        $users = User::where(function ($query) {
            $query->orWhere('age', 35)->orWhere('age', 33);
        })
        ->where(function ($query) {
            $query->orWhere('name', 'John Doe')->orWhere('name', 'Jane Doe');
        })->get();

        $this->assertCount(2, $users);
    }

    public function testPaginate()
    {
        $results = User::paginate(2);
        $this->assertEquals(2, $results->count());
        $this->assertNotNull($results->first()->title);
        $this->assertEquals(9, $results->total());

        $results = User::paginate(2, ['name', 'age']);
        $this->assertEquals(2, $results->count());
        $this->assertNull($results->first()->title);
        $this->assertEquals(9, $results->total());
        $this->assertEquals(1, $results->currentPage());
    }
}
