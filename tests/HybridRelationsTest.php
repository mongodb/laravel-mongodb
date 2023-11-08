<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests;

use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\DB;
use MongoDB\Laravel\Tests\Models\Book;
use MongoDB\Laravel\Tests\Models\Photo;
use MongoDB\Laravel\Tests\Models\Role;
use MongoDB\Laravel\Tests\Models\SqlBook;
use MongoDB\Laravel\Tests\Models\SqlPhoto;
use MongoDB\Laravel\Tests\Models\SqlRole;
use MongoDB\Laravel\Tests\Models\SqlUser;
use MongoDB\Laravel\Tests\Models\User;
use PDOException;

class HybridRelationsTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        try {
            DB::connection('sqlite')->select('SELECT 1');
        } catch (PDOException) {
            $this->markTestSkipped('SQLite connection is not available.');
        }

        SqlUser::executeSchema();
        SqlBook::executeSchema();
        SqlRole::executeSchema();
        SqlPhoto::executeSchema();
    }

    public function tearDown(): void
    {
        SqlUser::truncate();
        SqlBook::truncate();
        SqlRole::truncate();
        SqlPhoto::truncate();
        Photo::truncate();
        Book::truncate();
        User::truncate();
    }

    public function testSqlRelations()
    {
        $user = new SqlUser();
        $this->assertInstanceOf(SqlUser::class, $user);
        $this->assertInstanceOf(SQLiteConnection::class, $user->getConnection());

        // SQL User
        $user->name = 'John Doe';
        $user->save();
        $this->assertIsInt($user->id);

        // SQL has many
        $book = new Book(['title' => 'Game of Thrones']);
        $user->books()->save($book);
        $user = SqlUser::find($user->id); // refetch
        $this->assertCount(1, $user->books);

        // MongoDB belongs to
        $book = $user->books()->first(); // refetch
        $this->assertEquals('John Doe', $book->sqlAuthor->name);

        // SQL has one
        $role = new Role(['type' => 'admin']);
        $user->role()->save($role);
        $user = SqlUser::find($user->id); // refetch
        $this->assertEquals('admin', $user->role->type);

        // MongoDB belongs to
        $role = $user->role()->first(); // refetch
        $this->assertEquals('John Doe', $role->sqlUser->name);

        // MongoDB User
        $user       = new User();
        $user->name = 'John Doe';
        $user->save();

        // MongoDB has many
        $book = new SqlBook(['title' => 'Game of Thrones']);
        $user->sqlBooks()->save($book);
        $user = User::find($user->_id); // refetch
        $this->assertCount(1, $user->sqlBooks);

        // SQL belongs to
        $book = $user->sqlBooks()->first(); // refetch
        $this->assertEquals('John Doe', $book->author->name);

        // MongoDB has one
        $role = new SqlRole(['type' => 'admin']);
        $user->sqlRole()->save($role);
        $user = User::find($user->_id); // refetch
        $this->assertEquals('admin', $user->sqlRole->type);

        // SQL belongs to
        $role = $user->sqlRole()->first(); // refetch
        $this->assertEquals('John Doe', $role->user->name);
    }

    public function testHybridWhereHas()
    {
        $user      = new SqlUser();
        $otherUser = new SqlUser();
        $this->assertInstanceOf(SqlUser::class, $user);
        $this->assertInstanceOf(SQLiteConnection::class, $user->getConnection());
        $this->assertInstanceOf(SqlUser::class, $otherUser);
        $this->assertInstanceOf(SQLiteConnection::class, $otherUser->getConnection());

        // SQL User
        $user->name = 'John Doe';
        $user->id   = 2;
        $user->save();
        // Other user
        $otherUser->name = 'Other User';
        $otherUser->id   = 3;
        $otherUser->save();
        // Make sure they are created
        $this->assertIsInt($user->id);
        $this->assertIsInt($otherUser->id);
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

        $users = SqlUser::whereHas('books', function ($query) {
            return $query->where('title', 'LIKE', 'Har%');
        })->get();

        $this->assertEquals(2, $users->count());

        $users = SqlUser::whereHas('books', function ($query) {
            return $query->where('title', 'LIKE', 'Harry%');
        }, '>=', 2)->get();

        $this->assertEquals(1, $users->count());

        $books = Book::whereHas('sqlAuthor', function ($query) {
            return $query->where('name', 'LIKE', 'Other%');
        })->get();

        $this->assertEquals(2, $books->count());
    }

    public function testHybridWith()
    {
        $user      = new SqlUser();
        $otherUser = new SqlUser();
        $this->assertInstanceOf(SqlUser::class, $user);
        $this->assertInstanceOf(SQLiteConnection::class, $user->getConnection());
        $this->assertInstanceOf(SqlUser::class, $otherUser);
        $this->assertInstanceOf(SQLiteConnection::class, $otherUser->getConnection());

        // SQL User
        $user->name = 'John Doe';
        $user->id   = 2;
        $user->save();
        // Other user
        $otherUser->name = 'Other User';
        $otherUser->id   = 3;
        $otherUser->save();
        // Make sure they are created
        $this->assertIsInt($user->id);
        $this->assertIsInt($otherUser->id);
        // Clear to start
        Book::truncate();
        SqlBook::truncate();
        // Create books
        // SQL relation
        $user->sqlBooks()->saveMany([
            new SqlBook(['title' => 'Game of Thrones']),
            new SqlBook(['title' => 'Harry Potter']),
        ]);

        $otherUser->sqlBooks()->saveMany([
            new SqlBook(['title' => 'Harry Plants']),
            new SqlBook(['title' => 'Harveys']),
            new SqlBook(['title' => 'Harry Planter']),
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

        SqlUser::with('books')->get()
            ->each(function ($user) {
                $this->assertEquals($user->id, $user->books->count());
            });

        SqlUser::whereHas('sqlBooks', function ($query) {
            return $query->where('title', 'LIKE', 'Harry%');
        })
            ->with('books')
            ->get()
            ->each(function ($user) {
                $this->assertEquals($user->id, $user->books->count());
            });
    }

    public function testHybridMorphToMongoDB(): void
    {
        $user = SqlUser::create(['name' => 'John Doe']);

        $photo = Photo::create(['url' => 'http://graph.facebook.com/john.doe/picture']);
        $photo = $user->photos()->save($photo);

        $this->assertEquals(1, $user->photos->count());
        $this->assertEquals($photo->id, $user->photos->first()->id);

        $user = SqlUser::find($user->id);
        $this->assertEquals(1, $user->photos->count());
        $this->assertEquals($photo->id, $user->photos->first()->id);

        $book = SqlBook::create(['title' => 'Game of Thrones']);
        $photo = Photo::create(['url' => 'http://graph.facebook.com/gameofthrones/picture']);
        $book->photo()->save($photo);

        $this->assertNotNull($book->photo);
        $this->assertEquals($photo->id, $book->photo->id);

        $book = SqlBook::where('title', $book->title)->get()->first();
        $this->assertNotNull($book->photo);
        $this->assertEquals($photo->id, $book->photo->id);

        $photo = Photo::first();
        $this->assertEquals($photo->hasImage->name, $user->name);
    }

    public function testHybridMorphToSql(): void
    {
        $user = User::create(['name' => 'John Doe']);

        $photo = SqlPhoto::create(['url' => 'http://graph.facebook.com/john.doe/picture']);
        $photo->save();
        $photo = $user->photos()->save($photo);

        $this->assertEquals(1, $user->photos->count());
        $this->assertEquals($photo->id, $user->photos->first()->id);

        $user = User::find($user->id);
        $this->assertEquals(1, $user->photos->count());
        $this->assertEquals($photo->id, $user->photos->first()->id);

        $book = Book::create(['title' => 'Game of Thrones']);
        $photo = SqlPhoto::create(['url' => 'http://graph.facebook.com/gameofthrones/picture']);
        $book->photo()->save($photo);

        $this->assertNotNull($book->photo);
        $this->assertEquals($photo->id, $book->photo->id);

        $book = Book::where('title', $book->title)->get()->first();
        $this->assertNotNull($book->photo);
        $this->assertEquals($photo->id, $book->photo->id);

        $photo = SqlPhoto::first();
        $this->assertEquals($photo->hasImage->name, $user->name);
    }
}
