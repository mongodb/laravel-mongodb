<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use MongoDB\Laravel\Tests\Models\Book;
use MongoDB\Laravel\Tests\Models\Client;
use MongoDB\Laravel\Tests\Models\Item;
use MongoDB\Laravel\Tests\Models\Label;
use MongoDB\Laravel\Tests\Models\Photo;
use MongoDB\Laravel\Tests\Models\Role;
use MongoDB\Laravel\Tests\Models\Soft;
use MongoDB\Laravel\Tests\Models\User;
use MongoDB\Laravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use function assert;

class WithAggregateTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::connection('mongodb')->disableQueryLog();

        User::truncate();
        Book::truncate();
        Item::truncate();
        Role::truncate();
        Client::truncate();
        Label::truncate();
        Photo::truncate();
        Soft::truncate();

        parent::tearDown();
    }

    public function testWithCountHasMany(): void
    {
        $author = User::create(['name' => 'Alice']);
        $author->books()->create(['title' => 'A']);
        $author->books()->create(['title' => 'B']);
        User::create(['name' => 'Bob']);

        $users = User::withCount('books')->orderBy('name')->get();

        self::assertSame(2, $users[0]->books_count);
        self::assertSame(0, $users[1]->books_count);
    }

    public function testWithExistsHasMany(): void
    {
        $author = User::create(['name' => 'Alice']);
        $author->books()->create(['title' => 'A']);
        User::create(['name' => 'Bob']);

        $users = User::withExists('books')->orderBy('name')->get();

        self::assertTrue($users[0]->books_exists);
        self::assertFalse($users[1]->books_exists);
    }

    #[DataProvider('provideAggregateFunctions')]
    public function testWithAggregateHasMany(string $method, string $alias, mixed $expected, mixed $default): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->items()->create(['name' => 'knife', 'amount' => 4]);
        $user->items()->create(['name' => 'fork', 'amount' => 6]);
        User::create(['name' => 'Bob']);

        $users = User::$method('items', 'amount')->orderBy('name')->get();

        self::assertSame($expected, $users[0]->$alias);
        self::assertSame($default, $users[1]->$alias);
    }

    public static function provideAggregateFunctions(): iterable
    {
        yield 'sum' => ['withSum', 'items_sum_amount', 10, null];
        yield 'avg' => ['withAvg', 'items_avg_amount', 5.0, null];
        yield 'min' => ['withMin', 'items_min_amount', 4, null];
        yield 'max' => ['withMax', 'items_max_amount', 6, null];
    }

    public function testWithAggregateUsesASingleQueryPerAggregate(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->books()->create(['title' => 'A']);
        $user->items()->create(['name' => 'knife', 'amount' => 4]);
        $user->role()->create(['type' => 'admin']);
        User::create(['name' => 'Bob']);

        $connection = DB::connection('mongodb');
        $connection->enableQueryLog();

        $users = User::withCount('books')
            ->withMax('items', 'amount')
            ->withExists('role')
            ->orderBy('name')
            ->get();

        self::assertCount(4, $connection->getQueryLog());

        self::assertSame(1, $users[0]->books_count);
        self::assertSame(4, $users[0]->items_max_amount);
        self::assertTrue($users[0]->role_exists);

        self::assertSame(0, $users[1]->books_count);
        self::assertNull($users[1]->items_max_amount);
        self::assertFalse($users[1]->role_exists);
    }

    public function testWithCountAlias(): void
    {
        $author = User::create(['name' => 'Alice']);
        $author->books()->create(['title' => 'A']);

        $user = User::withCount('books as book_total')->withMax('items as top_amount', 'amount')->first();

        self::assertSame(1, $user->book_total);
        self::assertNull($user->top_amount);
    }

    public function testWithCountConstraints(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->items()->create(['name' => 'knife', 'amount' => 4]);
        $user->items()->create(['name' => 'fork', 'amount' => 6]);
        $user->items()->create(['name' => 'spoon', 'amount' => 8]);

        $filter = static fn (Builder $query) => $query->where('amount', '<=', 6);

        self::assertSame(2, User::withCount(['items' => $filter])->first()->items_count);
        self::assertSame(6, User::withMax(['items' => $filter], 'amount')->first()->items_max_amount);
        self::assertSame(2, User::withCount(['items as cheap' => $filter])->first()->cheap);
    }

    public function testWithCountAppliesGlobalScopesOfRelated(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->softs()->create(['title' => 'A']);
        $user->softs()->create(['title' => 'B'])->delete();

        $user = User::withCount('softs')->withCount('softsWithTrashed')->first();

        self::assertSame(1, $user->softs_count);
        self::assertSame(2, $user->softs_with_trashed_count);
    }

    public function testWithCountHasOne(): void
    {
        User::create(['name' => 'Alice'])->role()->create(['type' => 'admin']);
        User::create(['name' => 'Bob']);

        $users = User::withCount('role')->orderBy('name')->get();

        self::assertSame(1, $users[0]->role_count);
        self::assertSame(0, $users[1]->role_count);
    }

    public function testWithCountMorphMany(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->photos()->create(['url' => 'user.jpg']);
        Client::create(['name' => 'Acme'])->photo()->create(['url' => 'client.jpg']);

        $user = User::withCount('photos')->first();

        self::assertSame(1, $user->photos_count);
    }

    public function testWithAggregateBelongsTo(): void
    {
        $author = User::create(['name' => 'Alice', 'age' => 37]);
        $author->books()->create(['title' => 'A']);
        Book::create(['title' => 'Anonymous']);

        $books = Book::withCount('author')->withExists('author')->withMax('author', 'age')->orderBy('title')->get();

        self::assertSame(1, $books[0]->author_count);
        self::assertTrue($books[0]->author_exists);
        self::assertSame(37, $books[0]->author_max_age);

        self::assertSame(0, $books[1]->author_count);
        self::assertFalse($books[1]->author_exists);
        self::assertNull($books[1]->author_max_age);
    }

    public function testWithCountBelongsToMany(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->clients()->create(['name' => 'Acme']);
        $user->clients()->create(['name' => 'Globex']);
        User::create(['name' => 'Bob']);

        $users = User::withCount('clients')->withExists('clients')->orderBy('name')->get();

        self::assertSame(2, $users[0]->clients_count);
        self::assertTrue($users[0]->clients_exists);
        self::assertSame(0, $users[1]->clients_count);
        self::assertFalse($users[1]->clients_exists);
    }

    public function testWithCountMorphToMany(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->labels()->create(['name' => 'red']);
        $user->labels()->create(['name' => 'blue']);
        Client::create(['name' => 'Acme'])->labels()->create(['name' => 'green']);

        $user = User::withCount('labels')->first();
        self::assertSame(2, $user->labels_count);

        // Inverse relation, the parent keys are stored in the pivot column of the label
        $label = Label::where('name', 'green')->withCount('clients')->first();
        self::assertSame(1, $label->clients_count);
    }

    public function testWithAggregateEmbedsMany(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->addresses()->create(['city' => 'Paris', 'zip' => 75001]);
        $user->addresses()->create(['city' => 'Lyon', 'zip' => 69001]);
        User::create(['name' => 'Bob']);

        $connection = DB::connection('mongodb');
        $connection->enableQueryLog();

        $users = User::withCount('addresses')
            ->withExists('addresses')
            ->withMax('addresses', 'zip')
            ->withMin('addresses', 'zip')
            ->withSum('addresses', 'zip')
            ->withAvg('addresses', 'zip')
            ->orderBy('name')
            ->get();

        // Embedded documents are read with the parent document
        self::assertCount(1, $connection->getQueryLog());

        self::assertSame(2, $users[0]->addresses_count);
        self::assertTrue($users[0]->addresses_exists);
        self::assertSame(75001, $users[0]->addresses_max_zip);
        self::assertSame(69001, $users[0]->addresses_min_zip);
        self::assertSame(144002, $users[0]->addresses_sum_zip);
        self::assertSame(72001, $users[0]->addresses_avg_zip);

        self::assertSame(0, $users[1]->addresses_count);
        self::assertFalse($users[1]->addresses_exists);
        self::assertNull($users[1]->addresses_max_zip);
        self::assertNull($users[1]->addresses_min_zip);
        self::assertNull($users[1]->addresses_sum_zip);
        self::assertNull($users[1]->addresses_avg_zip);
    }

    public function testWithAggregateEmbedsOne(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->father()->save(new User(['name' => 'Mark Doe', 'age' => 70]));
        User::create(['name' => 'Bob']);

        $users = User::withCount('father')->withExists('father')->orderBy('name')->get();

        self::assertSame(1, $users[0]->father_count);
        self::assertTrue($users[0]->father_exists);
        self::assertSame(0, $users[1]->father_count);
        self::assertFalse($users[1]->father_exists);
    }

    public function testAggregateIsAddedToArray(): void
    {
        User::create(['name' => 'Alice'])->books()->create(['title' => 'A']);

        $user = User::withCount('books')->first();

        self::assertSame(1, $user->toArray()['books_count']);
    }

    public function testLoadCountAndLoadExists(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->books()->create(['title' => 'A']);

        $user = User::first();
        $user->loadCount('books');
        $user->loadExists('role');

        self::assertSame(1, $user->books_count);
        self::assertFalse($user->role_exists);

        $users = User::all();
        $users->loadCount('books');

        self::assertSame(1, $users[0]->books_count);
    }

    public function testWithCountWithSelectAndPaginate(): void
    {
        $author = User::create(['name' => 'Alice']);
        $author->books()->create(['title' => 'A']);
        User::create(['name' => 'Bob']);

        $users = User::withCount('books')->select('name')->orderBy('name')->paginate(1);

        self::assertSame(2, $users->total());
        self::assertSame(1, $users[0]->books_count);
        self::assertSame('Alice', $users[0]->name);
    }

    public function testOrderByAggregateIsNotSupported(): void
    {
        User::create(['name' => 'Alice']);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Ordering by the aggregated field "books_count" is not supported');

        User::withCount('books')->orderBy('books_count')->get();
    }

    public function testHybridRelationIsNotSupported(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Aggregating the hybrid relation "sqlBooks" is not supported');

        User::withCount('sqlBooks')->get();
    }

    public function testMorphToIsNotSupported(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('MorphTo is not supported for relation aggregates');

        Photo::withCount('hasImage')->get();
    }

    public function testConstraintsOnEmbeddedRelationAreNotSupported(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Constraints on the embedded relation "addresses" are not supported');

        User::withCount(['addresses' => static fn (Builder $query) => $query->where('city', 'Paris')])->get();
    }

    public function testLimitOnEmbeddedRelationIsNotSupported(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Constraints on the embedded relation "addresses" are not supported');

        User::withCount(['addresses' => static fn (Builder $query) => $query->limit(1)])->get();
    }

    public function testUnsupportedFunction(): void
    {
        $builder = User::query();
        assert($builder instanceof \MongoDB\Laravel\Eloquent\Builder);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Aggregate function "stdDevPop" is not supported by MongoDB');

        $builder->withAggregate('books', 'title', 'stdDevPop');
    }

    public function testColumnStartingWithDollarIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The aggregate column name "$ROOT" must not start with "$"');

        User::withSum('books', '$ROOT');
    }

    public function testNonScalarRelationKeyIsRejected(): void
    {
        $author = User::create(['name' => 'Alice']);
        $author->books()->create(['title' => 'A']);
        User::insert([['name' => 'Bob', '_id' => ['nested' => 'key']]]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The relation key of type "array" cannot be used to match aggregated values.');

        User::withCount('books')->get();
    }
}
