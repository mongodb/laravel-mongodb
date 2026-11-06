<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Eloquent;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use MongoDB\Laravel\Tests\Models\Item;
use MongoDB\Laravel\Tests\Models\User;
use MongoDB\Laravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class RelationAggregateTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::connection('mongodb')->disableQueryLog();

        User::truncate();
        Item::truncate();

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

    public function testOrderByRelationAggregateUnsupportedDirection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Order direction must be "asc" or "desc", "up" given.');

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
