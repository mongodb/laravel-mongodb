<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Eloquent;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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

use function array_column;
use function array_keys;
use function assert;
use function collect;

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

    /** @param Closure(): Collection<int, User> $users */
    #[DataProvider('provideEmbeddedAggregatesOfANarrowedSelection')]
    public function testAnEmbeddedAggregateReadsTheEmbeddedDocumentsOutsideTheSelection(Closure $users, string $attribute, array $expected): void
    {
        $alice = User::create(['name' => 'Alice']);
        $alice->addresses()->create(['city' => 'Paris']);
        $alice->addresses()->create(['city' => 'Lyon']);
        $alice->father()->save(new User(['name' => 'Mark']));
        User::create(['name' => 'Bob'])->addresses()->create(['city' => 'Lyon']);
        User::create(['name' => 'Carol']);

        self::assertSame($expected, $users()->pluck($attribute)->all());
    }

    /** @param Closure(): Builder<User> $query */
    #[DataProvider('provideSelectionsWithAnEmbeddedAggregate')]
    public function testAnEmbeddedAggregateKeepsTheSelection(Closure $query, array $attributes, ?array $cities): void
    {
        User::create(['name' => 'Alice', 'age' => 30])->addresses()->create(['city' => 'Paris', 'zip' => 75001]);

        $user = $query()->where('name', 'Alice')->first();

        self::assertEqualsCanonicalizing($attributes, array_keys($user->getAttributes()));
        self::assertSame(1, $user->addresses_count);
        self::assertSame($cities, isset($user->getAttributes()['addresses']) ? array_column($user->getAttributes()['addresses'], 'city') : null);
    }

    /** @param Closure(): iterable<User> $users */
    #[DataProvider('provideNarrowedReadsWithAnEmbeddedAggregate')]
    public function testAnEmbeddedAggregateOfANarrowedSelectionWorksWithEveryRead(Closure $users, array $counts): void
    {
        $alice = User::create(['name' => 'Alice']);
        $alice->addresses()->create(['city' => 'Paris']);
        $alice->addresses()->create(['city' => 'Lyon']);
        $alice->items()->create(['name' => 'a', 'amount' => 3]);
        $bob = User::create(['name' => 'Bob']);
        $bob->addresses()->create(['city' => 'Paris']);
        $bob->items()->create(['name' => 'b', 'amount' => 5]);
        $bob->items()->create(['name' => 'c', 'amount' => 5]);

        $users = collect($users());

        self::assertSame($counts, $users->pluck('addresses_count', 'name')->all());
        self::assertEqualsCanonicalizing(['id', 'name', 'addresses_count'], array_keys($users->first()->getAttributes()));
    }

    public static function provideNarrowedReadsWithAnEmbeddedAggregate(): iterable
    {
        yield 'paginate' => [
            static fn () => User::select('name')->withCount('addresses')->orderBy('name')->paginate(1)->items(),
            ['Alice' => 2],
        ];
        yield 'chunk' => [
            static function () {
                $users = [];
                User::select('name')->withCount('addresses')->orderBy('name')->chunk(1, static function ($chunk) use (&$users) {
                    $users = [...$users, ...$chunk];
                });

                return $users;
            },
            ['Alice' => 2, 'Bob' => 1],
        ];
        yield 'loadCount after a narrowed read' => [
            static fn () => User::select('name')->orderBy('name')->get()->loadCount('addresses'),
            ['Alice' => 2, 'Bob' => 1],
        ];
        yield 'with an eager loaded relation' => [
            static fn () => User::select('name')->with('items')->withCount('addresses')->orderBy('name')->get(),
            ['Alice' => 2, 'Bob' => 1],
        ];
        yield 'with a check for an embedded document' => [
            static fn () => User::select('name')->withCount('addresses')->whereHas('addresses', static fn ($query) => $query->where('city', 'Lyon'))->get(),
            ['Alice' => 2],
        ];
    }

    public function testAReusedQueryComputesAnotherAggregateAfterAnEmbeddedAggregate(): void
    {
        User::create(['name' => 'Alice', 'age' => 30])->addresses()->create(['city' => 'Paris']);
        User::create(['name' => 'Bob', 'age' => 40]);
        $query = User::select('name')->withCount('addresses');

        $query->get();

        self::assertSame(70, $query->sum('age'));
    }

    public function testSeveralEmbeddedAggregatesOfANarrowedSelection(): void
    {
        $alice = User::create(['name' => 'Alice']);
        $alice->addresses()->create(['city' => 'Paris', 'zip' => 75001]);
        $alice->addresses()->create(['city' => 'Lyon', 'zip' => 69001]);
        $alice->father()->save(new User(['name' => 'Mark']));
        User::create(['name' => 'Bob'])->addresses()->create(['city' => 'Lyon', 'zip' => 69001]);

        $users = User::select('name')
            ->withCount('addresses')
            ->withSum('addresses', 'zip')
            ->withExists('father')
            ->withCount('books')
            ->orderBy('name')
            ->get();

        self::assertSame([2, 1], $users->pluck('addresses_count')->all());
        self::assertSame([144002, 69001], $users->pluck('addresses_sum_zip')->all());
        self::assertSame([true, false], $users->pluck('father_exists')->all());
        self::assertSame([0, 0], $users->pluck('books_count')->all());
        self::assertEqualsCanonicalizing(
            ['id', 'name', 'addresses_count', 'addresses_sum_zip', 'father_exists', 'books_count'],
            array_keys($users->first()->getAttributes()),
        );
    }

    public function testAGroupedQueryKeepsItsSelection(): void
    {
        User::create(['name' => 'Alice'])->addresses()->create(['city' => 'Paris']);

        $group = User::select('name')->withCount('addresses')->groupBy('name')->first();

        self::assertEqualsCanonicalizing(['id', 'name', 'addresses_count'], array_keys($group->getAttributes()));
    }

    /** @param Closure(Builder<User>): void $firstRead */
    #[DataProvider('provideReusedQueriesWithAnEmbeddedAggregate')]
    public function testAReusedQueryReadsTheSelectionOfEachRead(Closure $firstRead, Closure $secondRead, array $attributes): void
    {
        User::create(['name' => 'Alice'])->addresses()->create(['city' => 'Paris']);
        $query = User::withCount('addresses');

        $firstRead($query);
        $user = $secondRead($query);

        self::assertEqualsCanonicalizing($attributes, array_keys($user->getAttributes()));
        self::assertSame(1, $user->addresses_count);
    }

    public static function provideReusedQueriesWithAnEmbeddedAggregate(): iterable
    {
        yield 'every field twice' => [
            static fn (Builder $query) => $query->get(),
            static fn (Builder $query) => $query->first(),
            ['id', 'name', 'addresses', 'addresses_count', 'updated_at', 'created_at'],
        ];
        yield 'widened after a narrowed read' => [
            static fn (Builder $query) => $query->select('name')->get(),
            static fn (Builder $query) => $query->select('*')->first(),
            ['id', 'name', 'addresses', 'addresses_count', 'updated_at', 'created_at'],
        ];
        yield 'narrowed after a full read' => [
            static fn (Builder $query) => $query->get(),
            static fn (Builder $query) => $query->select('name')->first(),
            ['id', 'name', 'addresses_count'],
        ];
    }

    public static function provideSelectionsWithAnEmbeddedAggregate(): iterable
    {
        yield 'a narrowed selection' => [
            static fn () => User::select('name')->withCount('addresses'),
            ['id', 'name', 'addresses_count'],
            null,
        ];
        yield 'every field' => [
            static fn () => User::select('*')->withCount('addresses'),
            ['id', 'name', 'age', 'addresses', 'addresses_count', 'updated_at', 'created_at'],
            ['Paris'],
        ];
        yield 'a field of the embedded documents' => [
            static fn () => User::select('name', 'addresses.city')->withCount('addresses'),
            ['id', 'name', 'addresses', 'addresses_count'],
            ['Paris'],
        ];
        yield 'the embedded documents selected afterwards' => [
            static fn () => User::select('name')->withCount('addresses')->addSelect('addresses'),
            ['id', 'name', 'addresses', 'addresses_count'],
            ['Paris'],
        ];
    }

    public static function provideEmbeddedAggregatesOfANarrowedSelection(): iterable
    {
        yield 'withCount on a selection' => [
            static fn
        () => User::select('name')->withCount('addresses')->orderBy('name')->get(),
            'addresses_count',
            [2, 1, 0],
        ];
        yield 'withExists on a selection' => [
            static fn
        () => User::select('name')->withExists('addresses')->orderBy('name')->get(),
            'addresses_exists',
            [true, true, false],
        ];
        yield 'loadCount' => [
            static fn
        () => User::orderBy('name')->get()->loadCount('addresses'),
            'addresses_count',
            [2, 1, 0],
        ];
        yield 'loadExists' => [
            static fn
        () => User::orderBy('name')->get()->loadExists('addresses'),
            'addresses_exists',
            [true, true, false],
        ];
        yield 'loadCount of embeds one' => [
            static fn
        () => User::orderBy('name')->get()->loadCount('father'),
            'father_count',
            [1, 0, 0],
        ];
        yield 'loadExists of embeds one' => [
            static fn
        () => User::orderBy('name')->get()->loadExists('father'),
            'father_exists',
            [true, false, false],
        ];
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

    public function testLoadSumAvgMinMax(): void
    {
        $user = User::create(['name' => 'Alice']);
        $user->items()->create(['name' => 'knife', 'amount' => 4]);
        $user->items()->create(['name' => 'fork', 'amount' => 6]);

        $user = User::first();
        $user->loadSum('items', 'amount');
        $user->loadAvg('items', 'amount');
        $user->loadMin('items', 'amount');
        $user->loadMax('items', 'amount');

        self::assertSame(10, $user->items_sum_amount);
        self::assertSame(5.0, $user->items_avg_amount);
        self::assertSame(4, $user->items_min_amount);
        self::assertSame(6, $user->items_max_amount);
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
