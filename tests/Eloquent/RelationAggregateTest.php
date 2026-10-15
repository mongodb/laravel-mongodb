<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Eloquent;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use MongoDB\Laravel\Tests\Models\User;
use MongoDB\Laravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class RelationAggregateTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::connection('mongodb')->disableQueryLog();

        User::truncate();

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

    public function testWhereRelationAggregate(): void
    {
        $this->seedUsers();

        $names = User::whereRelationAggregate('items', 'amount', 'sum', '>=', 15)
            ->orderBy('name')
            ->get()
            ->pluck('name')
            ->all();

        $this->assertSame(['Alice', 'Bob'], $names);
    }

    public function testWhereRelationAggregateCountsPagesWithTheMatch(): void
    {
        $this->seedUsers();

        $query = User::whereRelationAggregate('items', 'amount', 'sum', '<', 15);

        $this->assertSame(['Carol'], $query->orderBy('name')->get()->pluck('name')->all());
        $this->assertSame(1, $query->count());
        $this->assertSame(1, $query->paginate()->total());
    }

    public function testWhereRelationAggregateUnsupportedOperator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Comparison operator "~=" is not supported by MongoDB. Supported operators are: =, !=, <>, <, <=, >, >=');

        User::whereRelationAggregate('items', 'amount', 'sum', '~=', 1);
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
