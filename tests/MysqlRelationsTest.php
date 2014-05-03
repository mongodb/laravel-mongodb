<?php

class MysqlRelationsTest extends TestCase {

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
        $book = new Book(array('title' => 'Game of Thrones'));
        $user->books()->save($book);
        $user = MysqlUser::find($user->id); // refetch
        $this->assertEquals(1, count($user->books));

        // MongoDB belongs to
        $book = $user->books()->first(); // refetch
        $this->assertEquals('John Doe', $book->mysqlAuthor->name);

        // SQL has one
        $role = new Role(array('type' => 'admin'));
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
        $book = new MysqlBook(array('title' => 'Game of Thrones'));
        $user->mysqlBooks()->save($book);
        $user = User::find($user->_id); // refetch
        $this->assertEquals(1, count($user->mysqlBooks));

        // SQL belongs to
        $book = $user->mysqlBooks()->first(); // refetch
        $this->assertEquals('John Doe', $book->author->name);

        // MongoDB has one
        $role = new MysqlRole(array('type' => 'admin'));
        $user->mysqlRole()->save($role);
        $user = User::find($user->_id); // refetch
        $this->assertEquals('admin', $user->mysqlRole->type);

        // SQL belongs to
        $role = $user->mysqlRole()->first(); // refetch
        $this->assertEquals('John Doe', $role->user->name);
    }
}
