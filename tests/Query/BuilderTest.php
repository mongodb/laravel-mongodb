<?php

declare(strict_types=1);

namespace Jenssegers\Mongodb\Tests\Query;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;
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

        yield 'where with single array of conditions' => [
            ['find' => [
                ['$and' => [
                    ['foo' => 1],
                    ['bar' => 2],
                ]],
                [], // options
            ]],
            fn (Builder $builder) => $builder->where(['foo' => 1, 'bar' => 2]),
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

        /** @see DatabaseQueryBuilderTest::testBasicWhereNot() */
        yield 'whereNot (multiple)' => [
            ['find' => [
                ['$and' => [
                    ['$not' => ['name' => 'foo']],
                    ['$not' => ['name' => ['$ne' => 'bar']]],
                ]],
                [], // options
            ]],
            fn (Builder $builder) => $builder
                ->whereNot('name', 'foo')
                ->whereNot('name', '<>', 'bar'),
        ];

        /** @see DatabaseQueryBuilderTest::testBasicOrWheres() */
        yield 'where orWhere' => [
            ['find' => [
                ['$or' => [
                    ['id' => 1],
                    ['email' => 'foo'],
                ]],
                [], // options
            ]],
            fn (Builder $builder) => $builder
                ->where('id', '=', 1)
                ->orWhere('email', '=', 'foo'),
        ];

        /** @see DatabaseQueryBuilderTest::testBasicOrWhereNot() */
        yield 'orWhereNot' => [
            ['find' => [
                ['$or' => [
                    ['$not' => ['name' => 'foo']],
                    ['$not' => ['name' => ['$ne' => 'bar']]],
                ]],
                [], // options
            ]],
            fn (Builder $builder) => $builder
                ->orWhereNot('name', 'foo')
                ->orWhereNot('name', '<>', 'bar'),
        ];

        yield 'whereNot orWhere' => [
            ['find' => [
                ['$or' => [
                    ['$not' => ['name' => 'foo']],
                    ['name' => ['$ne' => 'bar']],
                ]],
                [], // options
            ]],
            fn (Builder $builder) => $builder
                ->whereNot('name', 'foo')
                ->orWhere('name', '<>', 'bar'),
        ];

        /** @see DatabaseQueryBuilderTest::testWhereNot() */
        yield 'whereNot callable' => [
            ['find' => [
                ['$not' => ['name' => 'foo']],
                [], // options
            ]],
            fn (Builder $builder) => $builder
                ->whereNot(fn (Builder $q) => $q->where('name', 'foo')),
        ];

        yield 'where whereNot' => [
            ['find' => [
                ['$and' => [
                    ['name' => 'bar'],
                    ['$not' => ['email' => 'foo']],
                ]],
                [], // options
            ]],
            fn (Builder $builder) => $builder
                ->where('name', '=', 'bar')
                ->whereNot(function (Builder $q) {
                    $q->where('email', '=', 'foo');
                }),
        ];

        yield 'whereNot (nested)' => [
            ['find' => [
                ['$not' => [
                    '$and' => [
                        ['name' => 'foo'],
                        ['$not' => ['email' => ['$ne' => 'bar']]],
                    ],
                ]],
                [], // options
            ]],
            fn (Builder $builder) => $builder
                ->whereNot(function (Builder $q) {
                    $q->where('name', '=', 'foo')
                      ->whereNot('email', '<>', 'bar');
                }),
        ];

        yield 'orWhere orWhereNot' => [
            ['find' => [
                ['$or' => [
                    ['name' => 'bar'],
                    ['$not' => ['email' => 'foo']],
                ]],
                [], // options
            ]],
            fn (Builder $builder) => $builder
                ->orWhere('name', '=', 'bar')
                ->orWhereNot(function (Builder $q) {
                    $q->where('email', '=', 'foo');
                }),
        ];

        yield 'where orWhereNot' => [
            ['find' => [
                ['$or' => [
                    ['name' => 'bar'],
                    ['$not' => ['email' => 'foo']],
                ]],
                [], // options
            ]],
            fn (Builder $builder) => $builder
                ->where('name', '=', 'bar')
                ->orWhereNot('email', '=', 'foo'),
        ];

        /** @see DatabaseQueryBuilderTest::testWhereNotWithArrayConditions() */
        yield 'whereNot with arrays of single condition' => [
            ['find' => [
                ['$not' => [
                    '$and' => [
                        ['foo' => 1],
                        ['bar' => 2],
                    ],
                ]],
                [], // options
            ]],
            fn (Builder $builder) => $builder
                ->whereNot([['foo', 1], ['bar', 2]]),
        ];

        yield 'whereNot with single array of conditions' => [
            ['find' => [
                ['$not' => [
                    '$and' => [
                        ['foo' => 1],
                        ['bar' => 2],
                    ],
                ]],
                [], // options
            ]],
            fn (Builder $builder) => $builder
                ->whereNot(['foo' => 1, 'bar' => 2]),
        ];

        yield 'whereNot with arrays of single condition with operator' => [
            ['find' => [
                ['$not' => [
                    '$and' => [
                        ['foo' => 1],
                        ['bar' => ['$lt' => 2]],
                    ],
                ]],
                [], // options
            ]],
            fn (Builder $builder) => $builder
                ->whereNot([
                    ['foo', 1],
                    ['bar', '<', 2],
                ]),
        ];

        /** @see DatabaseQueryBuilderTest::testForPage() */
        yield 'forPage' => [
            ['find' => [[], ['limit' => 20, 'skip' => 40]]],
            fn (Builder $builder) => $builder->forPage(3, 20),
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

        /** @see DatabaseQueryBuilderTest::testWhereBetweens() */
        yield 'whereBetween array of numbers' => [
            ['find' => [['id' => ['$gte' => 1, '$lte' => 2]], []]],
            fn (Builder $builder) => $builder->whereBetween('id', [1, 2]),
        ];

        yield 'whereBetween nested array of numbers' => [
            ['find' => [['id' => ['$gte' => [1], '$lte' => [2, 3]]], []]],
            fn (Builder $builder) => $builder->whereBetween('id', [[1], [2, 3]]),
        ];

        $period = now()->toPeriod(now()->addMonth());
        yield 'whereBetween CarbonPeriod' => [
            ['find' => [
                ['created_at' => ['$gte' => new UTCDateTime($period->start), '$lte' => new UTCDateTime($period->end)]],
                [], // options
            ]],
            fn (Builder $builder) => $builder->whereBetween('created_at', $period),
        ];

        yield 'whereBetween collection' => [
            ['find' => [['id' => ['$gte' => 1, '$lte' => 2]], []]],
            fn (Builder $builder) => $builder->whereBetween('id', collect([1, 2])),
        ];

        /** @see DatabaseQueryBuilderTest::testOrWhereBetween() */
        yield 'orWhereBetween array of numbers' => [
            ['find' => [
                ['$or' => [
                    ['id' => 1],
                    ['id' => ['$gte' => 3, '$lte' => 5]],
                ]],
                [], // options
            ]],
            fn (Builder $builder) => $builder
                ->where('id', '=', 1)
                ->orWhereBetween('id', [3, 5]),
        ];

        /** @link https://www.mongodb.com/docs/manual/reference/bson-type-comparison-order/#arrays */
        yield 'orWhereBetween nested array of numbers' => [
            ['find' => [
                ['$or' => [
                    ['id' => 1],
                    ['id' => ['$gte' => [4], '$lte' => [6, 8]]],
                ]],
                [], // options
            ]],
            fn (Builder $builder) => $builder
                ->where('id', '=', 1)
                ->orWhereBetween('id', [[4], [6, 8]]),
        ];

        yield 'orWhereBetween collection' => [
            ['find' => [
                ['$or' => [
                    ['id' => 1],
                    ['id' => ['$gte' => 3, '$lte' => 4]],
                ]],
                [], // options
            ]],
            fn (Builder $builder) => $builder
                ->where('id', '=', 1)
                ->orWhereBetween('id', collect([3, 4])),
        ];

        yield 'whereNotBetween array of numbers' => [
            ['find' => [
                ['$or' => [
                    ['id' => ['$lte' => 1]],
                    ['id' => ['$gte' => 2]],
                ]],
                [], // options
            ]],
            fn (Builder $builder) => $builder->whereNotBetween('id', [1, 2]),
        ];

        /** @see DatabaseQueryBuilderTest::testOrWhereNotBetween() */
        yield 'orWhereNotBetween array of numbers' => [
            ['find' => [
                ['$or' => [
                    ['id' => 1],
                    ['$or' => [
                        ['id' => ['$lte' => 3]],
                        ['id' => ['$gte' => 5]],
                    ]],
                ]],
                [], // options
            ]],
            fn (Builder $builder) => $builder
                ->where('id', '=', 1)
                ->orWhereNotBetween('id', [3, 5]),
        ];

        yield 'orWhereNotBetween nested array of numbers' => [
            ['find' => [
                ['$or' => [
                    ['id' => 1],
                    ['$or' => [
                        ['id' => ['$lte' => [2, 3]]],
                        ['id' => ['$gte' => [5]]],
                    ]],
                ]],
                [], // options
            ]],
            fn (Builder $builder) => $builder
                ->where('id', '=', 1)
                ->orWhereNotBetween('id', [[2, 3], [5]]),
        ];

        yield 'orWhereNotBetween collection' => [
            ['find' => [
                ['$or' => [
                    ['id' => 1],
                    ['$or' => [
                        ['id' => ['$lte' => 3]],
                        ['id' => ['$gte' => 4]],
                    ]],
                ]],
                [], // options
            ]],
            fn (Builder $builder) => $builder
                ->where('id', '=', 1)
                ->orWhereNotBetween('id', collect([3, 4])),
        ];

        yield 'distinct' => [
            ['distinct' => ['foo', [], []]],
            fn (Builder $builder) => $builder->distinct('foo'),
        ];

        yield 'groupBy' => [
            ['aggregate' => [
                [['$group' => ['_id' => ['foo' => '$foo'], 'foo' => ['$last' => '$foo']]]],
                [], // options
            ]],
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

        /** @see DatabaseQueryBuilderTest::testWhereBetweens */
        yield 'whereBetween array too short' => [
            \InvalidArgumentException::class,
            'Between $values must be a list with exactly two elements: [min, max]',
            fn (Builder $builder) => $builder->whereBetween('id', [1]),
        ];

        yield 'whereBetween array too short (nested)' => [
            \InvalidArgumentException::class,
            'Between $values must be a list with exactly two elements: [min, max]',
            fn (Builder $builder) => $builder->whereBetween('id', [[1, 2]]),
        ];

        yield 'whereBetween array too long' => [
            \InvalidArgumentException::class,
            'Between $values must be a list with exactly two elements: [min, max]',
            fn (Builder $builder) => $builder->whereBetween('id', [1, 2, 3]),
        ];

        yield 'whereBetween collection too long' => [
            \InvalidArgumentException::class,
            'Between $values must be a list with exactly two elements: [min, max]',
            fn (Builder $builder) => $builder->whereBetween('id', new Collection([1, 2, 3])),
        ];

        yield 'whereBetween array is not a list' => [
            \InvalidArgumentException::class,
            'Between $values must be a list with exactly two elements: [min, max]',
            fn (Builder $builder) => $builder->whereBetween('id', ['min' => 1, 'max' => 2]),
        ];
    }

    /** @dataProvider getEloquentMethodsNotSupported */
    public function testEloquentMethodsNotSupported(\Closure $callback)
    {
        $builder = self::getBuilder();

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('This method is not supported by MongoDB');

        $callback($builder);
    }

    public static function getEloquentMethodsNotSupported()
    {
        // Most of this methods can be implemented using aggregation framework
        // whereInRaw, whereNotInRaw, orWhereInRaw, orWhereNotInRaw, whereBetweenColumns

        yield 'toSql' => [fn (Builder $builder) => $builder->toSql()];
        yield 'toRawSql' => [fn (Builder $builder) => $builder->toRawSql()];

        /** @see DatabaseQueryBuilderTest::testBasicWhereColumn() */
        /** @see DatabaseQueryBuilderTest::testArrayWhereColumn() */
        yield 'whereColumn' => [fn (Builder $builder) => $builder->whereColumn('first_name', 'last_name')];
        yield 'orWhereColumn' => [fn (Builder $builder) => $builder->orWhereColumn('first_name', 'last_name')];

        /** @see DatabaseQueryBuilderTest::testWhereFulltextMySql() */
        yield 'whereFulltext' => [fn (Builder $builder) => $builder->whereFulltext('body', 'Hello World')];

        /** @see DatabaseQueryBuilderTest::testGroupBys() */
        yield 'groupByRaw' => [fn (Builder $builder) => $builder->groupByRaw('DATE(created_at)')];

        /** @see DatabaseQueryBuilderTest::testOrderBys() */
        yield 'orderByRaw' => [fn (Builder $builder) => $builder->orderByRaw('"age" ? desc', ['foo'])];

        /** @see DatabaseQueryBuilderTest::testInRandomOrderMySql */
        yield 'inRandomOrder' => [fn (Builder $builder) => $builder->inRandomOrder()];

        yield 'union' => [fn (Builder $builder) => $builder->union($builder)];
        yield 'unionAll' => [fn (Builder $builder) => $builder->unionAll($builder)];

        /** @see DatabaseQueryBuilderTest::testRawHavings */
        yield 'havingRaw' => [fn (Builder $builder) => $builder->havingRaw('user_foo < user_bar')];
        yield 'having' => [fn (Builder $builder) => $builder->having('baz', '=', 1)];
        yield 'havingBetween' => [fn (Builder $builder) => $builder->havingBetween('last_login_date', ['2018-11-16', '2018-12-16'])];
        yield 'orHavingRaw' => [fn (Builder $builder) => $builder->orHavingRaw('user_foo < user_bar')];
    }

    private static function getBuilder(): Builder
    {
        $connection = m::mock(Connection::class);
        $processor = m::mock(Processor::class);
        $connection->shouldReceive('getSession')->andReturn(null);

        return new Builder($connection, $processor);
    }
}
