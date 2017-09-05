<?php

class HybridRelationsTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        MysqlUser::executeSchema();
        MysqlBook::executeSchema();
        MysqlRole::executeSchema();
    }

    public function tearDown()
    {
        MysqlUser::truncate();
        MysqlBook::truncate();
        MysqlRole::truncate();
    }

    public function testMysqlRelations()
    {
        $user = new MysqlUser;
        $this->assertInstanceOf('MysqlUser', $user);
        $this->assertInstanceOf('Illuminate\Database\MySqlConnection', $user->getConnection());

        // Mysql User
        $user->name = "John Doe";
        $user->save();
        $this->assertTrue(is_int($user->id));

        // SQL has many
        $book = new Book(['title' => 'Game of Thrones']);
        $user->books()->save($book);
        $user = MysqlUser::find($user->id); // refetch
        $this->assertEquals(1, count($user->books));

        // MongoDB belongs to
        $book = $user->books()->first(); // refetch
        $this->assertEquals('John Doe', $book->mysqlAuthor->name);

        // SQL has one
        $role = new Role(['type' => 'admin']);
        $user->role()->save($role);
        $user = MysqlUser::find($user->id); // refetch
        $this->assertEquals('admin', $user->role->type);

        // MongoDB belongs to
        $role = $user->role()->first(); // refetch
        $this->assertEquals('John Doe', $role->mysqlUser->name);

        // MongoDB User
        $user = new User;
        $user->name = "John Doe";
        $user->save();

        // MongoDB has many
        $book = new MysqlBook(['title' => 'Game of Thrones']);
        $user->mysqlBooks()->save($book);
        $user = User::find($user->_id); // refetch
        $this->assertEquals(1, count($user->mysqlBooks));

        // SQL belongs to
        $book = $user->mysqlBooks()->first(); // refetch
        $this->assertEquals('John Doe', $book->author->name);

        // MongoDB has one
        $role = new MysqlRole(['type' => 'admin']);
        $user->mysqlRole()->save($role);
        $user = User::find($user->_id); // refetch
        $this->assertEquals('admin', $user->mysqlRole->type);

        // SQL belongs to
        $role = $user->mysqlRole()->first(); // refetch
        $this->assertEquals('John Doe', $role->user->name);
    }

    public function testHybridWhereHas()
    {
        $user = new MysqlUser;
        $otherUser = new MysqlUser;
        $this->assertInstanceOf('MysqlUser', $user);
        $this->assertInstanceOf('Illuminate\Database\MySqlConnection', $user->getConnection());
        $this->assertInstanceOf('MysqlUser', $otherUser);
        $this->assertInstanceOf('Illuminate\Database\MySqlConnection', $otherUser->getConnection());

        //MySql User
        $user->name = "John Doe";
        $user->id = 2;
        $user->save();
        // Other user
        $otherUser->name = 'Other User';
        $otherUser->id = 3;
        $otherUser->save();
        // Make sure they are created
        $this->assertTrue(is_int($user->id));
        $this->assertTrue(is_int($otherUser->id));
        // Clear to start
        $user->books()->truncate();
        $otherUser->books()->truncate();
        // Create books
        $otherUser->books()->saveMany([
            new Book(['title' => 'Harry Plants']),
            new Book(['title' => 'Harveys']),
        ]);
        // SQL has many
        $user->books()->saveMany([
            new Book(['title' => 'Game of Thrones']),
            new Book(['title' => 'Harry Potter']),
            new Book(['title' => 'Harry Planter']),
        ]);

        $users = MysqlUser::whereHas('books', function ($query) {
            return $query->where('title', 'LIKE', 'Har%');
        })->get();

        $this->assertEquals(2, $users->count());

        $users = MysqlUser::whereHas('books', function ($query) {
            return $query->where('title', 'LIKE', 'Harry%');
        }, '>=', 2)->get();

        $this->assertEquals(1, $users->count());

        $books = Book::whereHas('mysqlAuthor', function ($query) {
            return $query->where('name', 'LIKE', 'Other%');
        })->get();

        $this->assertEquals(2, $books->count());
    }

    public function testHybridWith()
    {
        $user = new MysqlUser;
        $otherUser = new MysqlUser;
        $this->assertInstanceOf('MysqlUser', $user);
        $this->assertInstanceOf('Illuminate\Database\MySqlConnection', $user->getConnection());
        $this->assertInstanceOf('MysqlUser', $otherUser);
        $this->assertInstanceOf('Illuminate\Database\MySqlConnection', $otherUser->getConnection());

        //MySql User
        $user->name = "John Doe";
        $user->id = 2;
        $user->save();
        // Other user
        $otherUser->name = 'Other User';
        $otherUser->id = 3;
        $otherUser->save();
        // Make sure they are created
        $this->assertTrue(is_int($user->id));
        $this->assertTrue(is_int($otherUser->id));
        // Clear to start
        Book::truncate();
        MysqlBook::truncate();
        // Create books
        // Mysql relation
        $user->mysqlBooks()->saveMany([
            new MysqlBook(['title' => 'Game of Thrones']),
            new MysqlBook(['title' => 'Harry Potter']),
        ]);

        $otherUser->mysqlBooks()->saveMany([
            new MysqlBook(['title' => 'Harry Plants']),
            new MysqlBook(['title' => 'Harveys']),
            new MysqlBook(['title' => 'Harry Planter']),
        ]);
        // SQL has many Hybrid
        $user->books()->saveMany([
            new Book(['title' => 'Game of Thrones']),
            new Book(['title' => 'Harry Potter']),
        ]);

        $otherUser->books()->saveMany([
            new Book(['title' => 'Harry Plants']),
            new Book(['title' => 'Harveys']),
            new Book(['title' => 'Harry Planter']),
        ]);

        MysqlUser::with('books')->get()
            ->each(function ($user) {
                $this->assertEquals($user->id, $user->books->count());
            });

        MysqlUser::whereHas('mysqlBooks', function ($query) {
            return $query->where('title', 'LIKE', 'Harry%');
        })
            ->with('books')
            ->get()
            ->each(function ($user) {
                $this->assertEquals($user->id, $user->books->count());
            });
    }
}
