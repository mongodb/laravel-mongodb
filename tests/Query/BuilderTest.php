<?php

declare(strict_types=1);

namespace Jenssegers\Mongodb\Tests\Query;

use DateTimeImmutable;
use Illuminate\Tests\Database\DatabaseQueryBuilderTest;
use Jenssegers\Mongodb\Connection;
use Jenssegers\Mongodb\Query\Builder;
use Jenssegers\Mongodb\Query\Processor;
use Mockery as m;
use MongoDB\BSON\UTCDateTime;
use PHPUnit\Framework\TestCase;

class BuilderTest extends TestCase
{
    /**
     * @dataProvider provideQueryBuilderToMql
     */
    public function testMql(array $expected, \Closure $build): void
    {
        $builder = $build(self::getBuilder());
        $this->assertInstanceOf(Builder::class, $builder);
        $mql = $builder->toMql();

        // Operations that return a Cursor expect a "typeMap" option.
        if (isset($expected['find'][1])) {
            $expected['find'][1]['typeMap'] = ['root' => 'array', 'document' => 'array'];
        }
        if (isset($expected['aggregate'][1])) {
            $expected['aggregate'][1]['typeMap'] = ['root' => 'array', 'document' => 'array'];
        }

        // Compare with assertEquals because the query can contain BSON objects.
        $this->assertEquals($expected, $mql, var_export($mql, true));
    }

    public static function provideQueryBuilderToMql(): iterable
    {
        /**
         * Builder::aggregate() and Builder::count() cannot be tested because they return the result,
         * without modifying the builder.
         */
        $date = new DateTimeImmutable('2016-07-12 15:30:00');

        yield 'find' => [
            ['find' => [['foo' => 'bar'], []]],
            fn (Builder $builder) => $builder->where('foo', 'bar'),
        ];

        yield 'find > date' => [
            ['find' => [['foo' => ['$gt' => new UTCDateTime($date)]], []]],
            fn (Builder $builder) => $builder->where('foo', '>', $date),
        ];

        yield 'find in array' => [
            ['find' => [['foo' => ['$in' => ['bar', 'baz']]], []]],
            fn (Builder $builder) => $builder->whereIn('foo', ['bar', 'baz']),
        ];

        yield 'find limit offset select' => [
            ['find' => [[], ['limit' => 10, 'skip' => 5, 'projection' => ['foo' => 1, 'bar' => 1]]]],
            fn (Builder $builder) => $builder->limit(10)->offset(5)->select('foo', 'bar'),
        ];

        /** @see DatabaseQueryBuilderTest::testOrderBys() */
        yield 'orderBy multiple columns' => [
            ['find' => [[], ['sort' => ['email' => 1, 'age' => -1]]]],
            fn (Builder $builder) => $builder
                ->orderBy('email')
                ->orderBy('age', 'desc'),
        ];

        yield 'orders = null' => [
            ['find' => [[], []]],
            function (Builder $builder) {
                $builder->orders = null;

                return $builder;
            },
        ];

        yield 'orders = []' => [
            ['find' => [[], []]],
            function (Builder $builder) {
                $builder->orders = [];

                return $builder;
            },
        ];

        yield 'multiple orders with direction' => [
            ['find' => [[], ['sort' => ['email' => -1, 'age' => 1]]]],
            fn (Builder $builder) => $builder
                ->orderBy('email', -1)
                ->orderBy('age', 1),
        ];

        yield 'orderByDesc' => [
            ['find' => [[], ['sort' => ['email' => -1]]]],
            fn (Builder $builder) => $builder->orderByDesc('email'),
        ];

        /** @see DatabaseQueryBuilderTest::testReorder() */
        yield 'reorder reset' => [
            ['find' => [[], []]],
            fn (Builder $builder) => $builder->orderBy('name')->reorder(),
        ];

        yield 'reorder column' => [
            ['find' => [[], ['sort' => ['name' => -1]]]],
            fn (Builder $builder) => $builder->orderBy('name')->reorder('name', 'desc'),
        ];

        /** @link https://www.mongodb.com/docs/manual/reference/method/cursor.sort/#text-score-metadata-sort */
        yield 'orderBy array meta' => [
            ['find' => [
                ['$text' => ['$search' => 'operating']],
                ['sort' => ['score' => ['$meta' => 'textScore']]],
            ]],
            fn (Builder $builder) => $builder
                ->where('$text', ['$search' => 'operating'])
                ->orderBy('score', ['$meta' => 'textScore']),
        ];

        yield 'distinct' => [
            ['distinct' => ['foo', [], []]],
            fn (Builder $builder) => $builder->distinct('foo'),
        ];

        yield 'groupBy' => [
            ['aggregate' => [[['$group' => ['_id' => ['foo' => '$foo'], 'foo' => ['$last' => '$foo']]]], []]],
            fn (Builder $builder) => $builder->groupBy('foo'),
        ];
    }

    /**
     * @dataProvider provideExceptions
     */
    public function testException($class, $message, \Closure $build): void
    {
        $builder = self::getBuilder();

        $this->expectException($class);
        $this->expectExceptionMessage($message);
        $build($builder);
    }

    public static function provideExceptions(): iterable
    {
        yield 'orderBy invalid direction' => [
            \InvalidArgumentException::class,
            'Order direction must be "asc" or "desc"',
            fn (Builder $builder) => $builder->orderBy('_id', 'dasc'),
        ];
    }

    private static function getBuilder(): Builder
    {
        $connection = m::mock(Connection::class);
        $processor = m::mock(Processor::class);
        $connection->shouldReceive('getSession')->andReturn(null);

        return new Builder($connection, $processor);
    }
}
