<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Eloquent;

use Closure;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use MongoDB\BSON\Binary;
use MongoDB\Laravel\Tests\Models\Book;
use MongoDB\Laravel\Tests\Models\Client;
use MongoDB\Laravel\Tests\Models\Item;
use MongoDB\Laravel\Tests\Models\Soft;
use MongoDB\Laravel\Tests\Models\User;
use MongoDB\Laravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;

use function array_keys;
use function collect;
use function sprintf;

class RelationAggregateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->skipIfRelationLookupIsNotSupported();
    }

    protected function tearDown(): void
    {
        DB::connection('mongodb')->disableQueryLog();

        User::truncate();
        Item::truncate();
        Client::truncate();
        Book::truncate();
        Soft::truncate();

        parent::tearDown();
    }

    #[DataProvider('provideOrderByRelationAggregate')]
    public function testOrderByRelationAggregate(string $function, string $column, string $direction, array $expected): void
    {
        $this->seedUsers();

        $names = User::orderByRelationAggregate('items', $column, $function, $direction)
            ->orderBy('name')
            ->get()
            ->pluck('name')
            ->all();

        $this->assertSame($expected, $names);
    }

    public static function provideOrderByRelationAggregate(): iterable
    {
        yield 'count desc' => ['count', '*', 'desc', ['Alice', 'Bob', 'Carol']];
        yield 'count asc' => ['count', '*', 'asc', ['Carol', 'Bob', 'Alice']];
        yield 'sum desc' => ['sum', 'amount', 'desc', ['Bob', 'Alice', 'Carol']];
        yield 'avg desc' => ['avg', 'amount', 'desc', ['Bob', 'Alice', 'Carol']];
        yield 'min asc' => ['min', 'amount', 'asc', ['Carol', 'Alice', 'Bob']];
        yield 'max asc' => ['max', 'amount', 'asc', ['Carol', 'Alice', 'Bob']];
        yield 'exists asc' => ['exists', '*', 'asc', ['Carol', 'Alice', 'Bob']];
    }

    public function testOrderByRelationAggregateConstrainsTheRelatedQuery(): void
    {
        $this->seedUsers();

        $names = User::orderByRelationAggregate(
            'items',
            '*',
            'count',
            'asc',
            fn ($query) => $query->where('amount', '>=', 10),
        )
            ->orderBy('name')
            ->get()
            ->pluck('name')
            ->all();

        $this->assertSame(['Alice', 'Carol', 'Bob'], $names);
    }

    public function testOrderByRelationAggregateUnsupportedFunction(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Aggregate function "median" is not supported by MongoDB. Supported functions are: avg, count, exists, max, min, sum.');

        User::orderByRelationAggregate('items', 'amount', 'median');
    }

    #[TestWith(['$NOW'])]
    #[TestWith(['$amount'])]
    public function testOrderByRelationAggregateRejectsAnExpressionAsColumn(string $column): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('The aggregate column name "%s" must not start with "$".', $column));

        User::orderByRelationAggregate('items', $column, 'min');
    }

    #[TestWith(['amount.*.parts.*.price'])]
    #[TestWith(['.*.amount'])]
    #[TestWith(['amount.*.'])]
    public function testOrderByRelationAggregateRejectsAnUnsupportedWildcardColumn(string $column): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('The aggregate column "%s" is not supported.', $column));

        User::orderByRelationAggregate('items', $column, 'sum');
    }

    public function testOrderByRelationAggregateUnsupportedDirection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Order direction must be "asc", "desc" or a case from the SortDirection enum.');

        User::orderByRelationAggregate('items', '*', 'count', 'up');
    }

    public function testReorderClearsTheRelationAggregateSort(): void
    {
        User::create(['name' => 'Alice']);

        $bob = User::create(['name' => 'Bob']);
        $bob->items()->create(['name' => 'b', 'amount' => 1]);

        $names = User::orderByRelationAggregate('items', '*', 'count', 'desc')
            ->reorder('name')
            ->get()
            ->pluck('name')
            ->all();

        $this->assertSame(['Alice', 'Bob'], $names);
    }

    public function testRepeatedAggregatesOnTheSameRelationKeepTheirOwnValue(): void
    {
        $alice = User::create(['name' => 'Alice']);
        $alice->items()->create(['name' => 'a', 'amount' => 1, 'quantity' => 100]);

        $bob = User::create(['name' => 'Bob']);
        $bob->items()->create(['name' => 'b', 'amount' => 50, 'quantity' => 1]);

        $names = User::orderByRelationAggregate('items', 'amount', 'sum', 'desc')
            ->orderByRelationAggregate('items', 'quantity', 'sum', 'desc')
            ->get()
            ->pluck('name')
            ->all();

        $this->assertSame(['Bob', 'Alice'], $names);
    }

    public function testTheAggregateColumnIsResolvedThroughTheRelatedGrammar(): void
    {
        $alice = User::create(['name' => 'Alice']);
        $alice->items()->create(['name' => 'a', 'stats' => ['amount' => 1]]);

        $bob = User::create(['name' => 'Bob']);
        $bob->items()->create(['name' => 'b', 'stats' => ['amount' => 5]]);

        $names = User::orderByRelationAggregate('items', 'stats->amount', 'sum', 'desc')
            ->get()
            ->pluck('name')
            ->all();

        $this->assertSame(['Bob', 'Alice'], $names);
    }

    public function testOrderByRelationAggregatePlacesAnEmptyRelationLastForDescendingSums(): void
    {
        $alice = User::create(['name' => 'Alice']);
        $alice->items()->create(['name' => 'a', 'amount' => -5]);

        User::create(['name' => 'Bob']);

        $names = User::orderByRelationAggregate('items', 'amount', 'sum', 'desc')
            ->get()
            ->pluck('name')
            ->all();

        $this->assertSame(['Alice', 'Bob'], $names);
    }

    public function testPaginationCountsTheParentsWithoutRunningTheLookup(): void
    {
        $this->seedUsers();

        $page = User::orderByRelationAggregate('items', '*', 'count', 'desc')->paginate(2);

        $this->assertSame(3, $page->total());
        $this->assertSame(['Alice', 'Bob'], $page->pluck('name')->all());
    }

    #[DataProvider('provideProjectionsInTheConstraints')]
    public function testAProjectionInTheConstraintsKeepsTheAggregateColumn(callable $constraint): void
    {
        $this->seedUsers();

        $names = User::orderByRelationAggregate('items', 'amount', 'sum', 'desc', $constraint)
            ->get()
            ->pluck('name')
            ->all();

        $this->assertSame(['Bob', 'Alice', 'Carol'], $names);
    }

    public static function provideProjectionsInTheConstraints(): iterable
    {
        yield 'select' => [static fn ($query) => $query->select('name')];
        yield 'project' => [static fn ($query) => $query->project(['name' => 1])];
        yield 'projection option' => [static fn ($query) => $query->options(['projection' => ['name' => 1]])];
    }

    public function testTheConstraintsCanIncludeTrashedRelatedDocuments(): void
    {
        $alice = User::create(['name' => 'Alice']);
        $alice->softs()->create(['name' => 'a'])->delete();
        $alice->softs()->create(['name' => 'b']);

        $bob = User::create(['name' => 'Bob']);
        $bob->softs()->create(['name' => 'c']);
        $bob->softs()->create(['name' => 'd']);

        $names = User::orderByRelationAggregate('softs', '*', 'count', 'desc', fn ($query) => $query->withTrashed())
            ->orderBy('name')
            ->get()
            ->pluck('name')
            ->all();

        $this->assertSame(['Alice', 'Bob'], $names);
    }

    public function testCursorPaginationIsNotSupported(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cursor pagination is not supported on a query sorted with orderByRelationAggregate().');

        User::orderByRelationAggregate('items', '*', 'count')->orderBy('id')->cursorPaginate(1);
    }

    public function testCursorPaginationWorksAfterReorderingAwayFromTheAggregate(): void
    {
        $this->seedUsers();

        $page = User::orderByRelationAggregate('items', '*', 'count', 'desc')
            ->reorder('name')
            ->cursorPaginate(2);

        $this->assertSame(['Alice', 'Bob'], $page->pluck('name')->all());
    }

    public function testCountOfAColumnExcludesNullAndMissingValues(): void
    {
        $alice = User::create(['name' => 'Alice']);
        $alice->items()->create(['name' => 'a', 'amount' => 1]);
        $alice->items()->create(['name' => 'b', 'amount' => null]);
        $alice->items()->create(['name' => 'c']);

        $bob = User::create(['name' => 'Bob']);
        $bob->items()->create(['name' => 'd', 'amount' => 2]);
        $bob->items()->create(['name' => 'e', 'amount' => 3]);

        $names = User::orderByRelationAggregate('items', 'amount', 'count', 'desc')
            ->orderBy('name')
            ->get()
            ->pluck('name')
            ->all();

        $this->assertSame(['Bob', 'Alice'], $names);
    }

    public function testCountOfAColumnCountsAnArrayContainingNull(): void
    {
        $alice = User::create(['name' => 'Alice']);
        $alice->items()->create(['name' => 'a', 'tags' => ['red', null]]);
        $alice->items()->create(['name' => 'b', 'tags' => ['blue']]);

        $bob = User::create(['name' => 'Bob']);
        $bob->items()->create(['name' => 'c', 'tags' => ['green']]);

        $names = User::orderByRelationAggregate('items', 'tags', 'count', 'desc')
            ->orderBy('name', 'desc')
            ->get()
            ->pluck('name')
            ->all();

        $this->assertSame(['Alice', 'Bob'], $names);
    }

    #[TestWith(['sum', 'desc'])]
    #[TestWith(['count', 'asc'])]
    public function testAWildcardColumnAggregatesTheElementsOfASubdocumentArray(string $function, string $direction): void
    {
        $alice = User::create(['name' => 'Alice']);
        $alice->items()->create(['name' => 'a', 'amount' => [['hidden' => 10]]]);

        $bob = User::create(['name' => 'Bob']);
        $bob->items()->create(['name' => 'b', 'amount' => [['hidden' => 3], ['hidden' => 4]]]);

        $names = User::orderByRelationAggregate('items', 'amount.*.hidden', $function, $direction)
            ->orderBy('name', 'desc')
            ->get()
            ->pluck('name')
            ->all();

        $this->assertSame(['Alice', 'Bob'], $names);
    }

    public function testAVariableOfTheOuterQueryIsVisibleInTheConstraints(): void
    {
        $this->seedUsers();

        $names = User::orderByRelationAggregate(
            'items',
            '*',
            'count',
            'desc',
            fn ($query) => $query->whereRaw(['$expr' => ['$in' => ['$name', '$$keys']]]),
        )
            ->orderBy('name', 'desc')
            ->options(['let' => ['keys' => ['a', 'b', 'd']]])
            ->get()
            ->pluck('name')
            ->all();

        $this->assertSame(['Alice', 'Bob', 'Carol'], $names);
    }

    #[TestWith(['sum', 'desc', ['Alice', 'Bob']])]
    #[TestWith(['count', 'asc', ['Bob', 'Alice']])]
    public function testASortAndLimitInTheConstraintsDoNotNarrowTheAggregatedDocuments(string $function, string $direction, array $expected): void
    {
        $alice = User::create(['name' => 'Alice']);
        $alice->items()->create(['name' => 'a', 'amount' => 10]);
        $alice->items()->create(['name' => 'b', 'amount' => 20]);

        $bob = User::create(['name' => 'Bob']);
        $bob->items()->create(['name' => 'c', 'amount' => 15]);

        $names = User::orderByRelationAggregate(
            'items',
            'amount',
            $function,
            $direction,
            fn ($query) => $query->orderBy('amount')->limit(1),
        )
            ->get()
            ->pluck('name')
            ->all();

        $this->assertSame($expected, $names);
    }

    public function testAnOffsetInTheConstraintsIsNotSupported(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('An "offset()" on a relationship aggregate constraint is not supported.');

        User::orderByRelationAggregate('items', 'amount', 'sum', 'desc', fn ($query) => $query->offset(1));
    }

    public function testTheSortFollowsTheValuesOfWithSum(): void
    {
        $alice = User::create(['name' => 'Alice']);
        $alice->items()->create(['name' => 'a', 'amount' => '9']);
        $alice->items()->create(['name' => 'b', 'amount' => true]);
        $alice->items()->create(['name' => 'c', 'amount' => 1]);

        $bob = User::create(['name' => 'Bob']);
        $bob->items()->create(['name' => 'd', 'amount' => 5]);

        $sums = User::withSum('items', 'amount')
            ->orderByRelationAggregate('items', 'amount', 'sum', 'desc')
            ->get()
            ->pluck('items_sum_amount', 'name')
            ->all();

        $this->assertSame(['Bob' => 5, 'Alice' => 1], $sums);
    }

    public function testTheAggregateIsComputedFromTheQueriedCollection(): void
    {
        $alice = User::create(['name' => 'Alice']);
        $alice->clients()->create(['name' => 'a']);
        $users = DB::connection('mongodb')->getCollection('users');
        $archivedUsers = DB::connection('mongodb')->getCollection('archived_users');
        $archivedUsers->insertOne($users->findOne(['name' => 'Alice']));
        $users->deleteMany([]);

        try {
            $counts = User::from('archived_users')->withCount('clients')->get()->pluck('clients_count', 'name')->all();
        } finally {
            $archivedUsers->drop();
        }

        $this->assertSame(['Alice' => 1], $counts);
    }

    /** @param Closure(): iterable<User> $users */
    #[DataProvider('provideSortsWithAnEmbeddedAggregate')]
    public function testAnEmbeddedAggregateWorksWithASortOnARelationAggregate(Closure $users, array $counts, array $attributes): void
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

        $this->assertSame($counts, $users->pluck('addresses_count', 'name')->all());
        $this->assertEqualsCanonicalizing($attributes, array_keys($users->first()->getAttributes()));
    }

    public static function provideSortsWithAnEmbeddedAggregate(): iterable
    {
        yield 'a narrowed selection' => [
            static fn () => User::select('name')->withCount('addresses')->orderByRelationAggregate('items', 'amount', 'sum', 'desc')->get(),
            ['Bob' => 1, 'Alice' => 2],
            ['id', 'name', 'addresses_count'],
        ];
        yield 'every field' => [
            static fn () => User::withCount('addresses')->orderByRelationAggregate('items', 'amount', 'sum', 'desc')->get(),
            ['Bob' => 1, 'Alice' => 2],
            ['id', 'name', 'addresses', 'addresses_count', 'created_at', 'updated_at'],
        ];
        yield 'a narrowed page' => [
            static fn () => User::select('name')->withCount('addresses')->orderByRelationAggregate('items', 'amount', 'sum', 'desc')->paginate(1)->items(),
            ['Bob' => 1],
            ['id', 'name', 'addresses_count'],
        ];
    }

    public function testAnAggregateKeepsItsValueWhenTheSelectionFollows(): void
    {
        $alice = User::create(['name' => 'Alice']);
        Book::create(['title' => 'A', 'author_id' => $alice->id]);

        $counts = Book::withCount('author')->select('author_id')->where('author_id', $alice->id)->get()->pluck('author_count')->all();

        $this->assertSame([1], $counts);
    }

    public function testAnAggregateIsMatchedByTheDocumentIdWhenTheDocumentHasItsOwnIdField(): void
    {
        $alice = User::create(['name' => 'Alice']);
        DB::connection('mongodb')->getCollection('books')->insertMany([
            ['id' => 'own', 'title' => 'A', 'author_id' => $alice->id],
            ['id' => 'own', 'title' => 'B'],
        ]);

        $this->assertSame(['A' => 1, 'B' => 0], Book::withCount('author')->orderBy('title')->get()->pluck('author_count', 'title')->all());
        $this->assertSame(['A' => 1, 'B' => 0], Book::select('title')->withCount('author')->orderBy('title')->get()->pluck('author_count', 'title')->all());
    }

    public function testAnAggregateKeepsDocumentIdsOfDifferentTypesApart(): void
    {
        $alice = User::create(['name' => 'Alice']);
        DB::connection('mongodb')->getCollection('books')->insertMany([
            ['_id' => 1, 'title' => 'A', 'author_id' => $alice->id],
            ['_id' => '1', 'title' => 'B'],
        ]);

        $this->assertSame(['A' => 1, 'B' => 0], Book::withCount('author')->orderBy('title')->get()->pluck('author_count', 'title')->all());
    }

    public function testAnAggregateWorksWithADocumentId(): void
    {
        $alice = User::create(['name' => 'Alice']);
        DB::connection('mongodb')->getCollection('books')->insertMany([
            ['_id' => ['tenant' => 1, 'book' => 2], 'title' => 'A', 'author_id' => $alice->id],
            ['_id' => ['tenant' => 1, 'book' => 3], 'title' => 'B'],
        ]);

        $this->assertSame(['A' => 1, 'B' => 0], Book::withCount('author')->orderBy('title')->get()->pluck('author_count', 'title')->all());
    }

    public function testAnAggregateWorksWithAGroupedQuery(): void
    {
        $alice = User::create(['name' => 'Alice']);
        Book::create(['title' => 'A', 'author_id' => $alice->id]);
        Book::create(['title' => 'B', 'author_id' => $alice->id]);

        $counts = Book::select('author_id')->groupBy('author_id')->withCount('author')->get()->pluck('author_count')->all();

        $this->assertSame([1], $counts);
    }

    public function testTheSelectionDoesNotChangeTheAggregate(): void
    {
        $alice = User::create(['name' => 'Alice', 'age' => '9', 'score' => 4]);
        Book::create(['title' => 'A', 'author_id' => $alice->id]);

        $books = Book::select('author_id')
            ->where('author_id', $alice->id)
            ->withCount('author')
            ->withSum('author', 'age')
            ->withSum('author', 'score')
            ->get();

        $this->assertSame([1], $books->pluck('author_count')->all());
        $this->assertSame([9], $books->pluck('author_sum_age')->all());
        $this->assertSame([4], $books->pluck('author_sum_score')->all());
    }

    #[TestWith(["\x01aaaaaaaaaaaaaaa"])]
    #[TestWith(['aaaaaaaaaaaaaaaa'])]
    public function testTheAggregateIsMatchedToAParentWithABinaryKey(string $uuid): void
    {
        $alice = User::create(['name' => 'Alice']);
        DB::connection('mongodb')->getCollection('items')->insertOne([
            '_id' => new Binary($uuid, Binary::TYPE_UUID),
            'user_id' => $alice->id,
        ]);

        $this->assertSame([1], Item::withCount('user')->get()->pluck('user_count')->all());
    }

    private function seedUsers(): void
    {
        $alice = User::create(['name' => 'Alice']);
        $alice->items()->create(['name' => 'a', 'amount' => 4]);
        $alice->items()->create(['name' => 'b', 'amount' => 6]);
        $alice->items()->create(['name' => 'c', 'amount' => 5]);

        $bob = User::create(['name' => 'Bob']);
        $bob->items()->create(['name' => 'd', 'amount' => 10]);
        $bob->items()->create(['name' => 'e', 'amount' => 10]);

        User::create(['name' => 'Carol']);
    }
}
