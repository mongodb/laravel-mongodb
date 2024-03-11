<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Query;

use ArgumentCountError;
use BadMethodCallException;
use Closure;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use LogicException;
use Mockery as m;
use MongoDB\BSON\Document;
use MongoDB\BSON\Regex;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Builder\BuilderEncoder;
use MongoDB\Laravel\Connection;
use MongoDB\Laravel\Query\AggregationBuilder;
use MongoDB\Laravel\Query\Builder;
use MongoDB\Laravel\Query\Grammar;
use MongoDB\Laravel\Query\Processor;
use PHPUnit\Framework\TestCase;
use stdClass;

use function array_merge;
use function collect;
use function method_exists;
use function now;
use function var_export;

class BuilderTest extends TestCase
{
    /** @dataProvider provideQueryBuilderToMql */
    public function testFind(?array $expectedFind, ?array $expectedPipeline, Closure $build): void
    {
        if ($expectedFind === null) {
            $this->markTestIncomplete('Not supported');
        }

        $builder = $build(self::getBuilder());
        $this->assertInstanceOf(Builder::class, $builder);
        $mql = $builder->toMql();

        $expected = [
            'find' => [
                $expectedFind[0],
                array_merge($expectedFind[1] ?? [], ['typeMap' => ['root' => 'array', 'document' => 'array']]),
            ],
        ];

        $expected = Document::fromPHP($expected)->toCanonicalExtendedJSON();
        $mql = Document::fromPHP($mql)->toCanonicalExtendedJSON();

        // Compare with assertEquals because the query can contain BSON objects.
        $this->assertEquals($expected, $mql, var_export($mql, true));
    }

    /** @dataProvider provideQueryBuilderToMql */
    public function testAggregate(?array $expectedFind, ?array $expectedPipeline, Closure $build): void
    {
        if ($expectedPipeline === null) {
            $this->markTestIncomplete('Not supported');
        }

        $builder = $build(self::getBuilder());
        $this->assertInstanceOf(Builder::class, $builder);
        $encoder = new BuilderEncoder();
        $pipelineBuilder = $builder->aggregate();
        $this->assertInstanceOf(AggregationBuilder::class, $pipelineBuilder);
        $pipeline = $encoder->encode($pipelineBuilder->getPipeline());

        $expected = Document::fromPHP($expectedPipeline)->toCanonicalExtendedJSON();
        $pipeline = Document::fromPHP($pipeline)->toCanonicalExtendedJSON();

        // Compare with assertEquals because the query can contain BSON objects.
        $this->assertJsonStringEqualsJsonString($expected, $pipeline);
    }

    public static function provideQueryBuilderToMql(): iterable
    {
        /**
         * Builder::aggregate() and Builder::count() cannot be tested because they return the result,
         * without modifying the builder.
         */
        $date = new DateTimeImmutable('2016-07-12 15:30:00');

        yield 'select replaces previous select' => [
            'find' => [[], ['projection' => ['bar' => true]]],
            'aggregate' => [
                ['$project' => ['bar' => true]],
            ],
            fn (Builder $builder) => $builder->select('foo')->select('bar'),
        ];

        yield 'select array' => [
            'find' => [[], ['projection' => ['foo' => true, 'bar' => true]]],
            'aggregate' => [
                ['$project' => ['foo' => true, 'bar' => true]],
            ],
            fn (Builder $builder) => $builder->select(['foo', 'bar']),
        ];

        /** @see DatabaseQueryBuilderTest::testAddingSelects */
        yield 'addSelect' => [
            'find' => [[], ['projection' => ['foo' => true, 'bar' => true, 'baz' => true, 'boom' => true]]],
            'aggregate' => [
                ['$project' => ['foo' => true, 'bar' => true, 'baz' => true, 'boom' => true]],
            ],
            fn (Builder $builder) => $builder->select('foo')
                ->addSelect('bar')
                ->addSelect(['baz', 'boom'])
                ->addSelect('bar'),
        ];

        yield 'select all' => [
            'find' => [[]],
            'aggregate' => [],
            fn (Builder $builder) => $builder->select('*'),
        ];

        yield 'find all with select' => [
            'find' => [[], ['projection' => ['foo' => true, 'bar' => true]]],
            'aggregate' => [
                ['$project' => ['foo' => true, 'bar' => true]],
            ],
            fn (Builder $builder) => $builder->select('foo', 'bar'),
        ];

        yield 'find equals' => [
            'find' => [['foo' => 'bar'], []],
            'aggregate' => [
                ['$match' => ['foo' => 'bar']],
            ],
            fn (Builder $builder) => $builder->where('foo', 'bar'),
        ];

        yield 'find with numeric field name' => [
            'find' => [['123' => 'bar'], []],
            'aggregate' => null, // Array unpacking with numeric keys resets the key number. https://3v4l.org/M9lrq
            fn (Builder $builder) => $builder->where(123, 'bar'),
        ];

        yield 'where with single array of conditions' => [
            'find' => [
                [
                    '$and' => [
                        ['foo' => 1],
                        ['bar' => 2],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$and' => [
                            ['foo' => 1],
                            ['bar' => 2],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder->where(['foo' => 1, 'bar' => 2]),
        ];

        yield 'find > date' => [
            'find' => [['foo' => ['$gt' => new UTCDateTime($date)]]],
            'aggregate' => [
                [
                    '$match' => ['foo' => ['$gt' => new UTCDateTime($date)]],
                ],
            ],
            fn (Builder $builder) => $builder->where('foo', '>', $date),
        ];

        /** @see DatabaseQueryBuilderTest::testBasicWhereIns */
        yield 'whereIn' => [
            'find' => [['foo' => ['$in' => ['bar', 'baz']]]],
            'aggregate' => [
                [
                    '$match' => ['foo' => ['$in' => ['bar', 'baz']]],
                ],
            ],
            fn (Builder $builder) => $builder->whereIn('foo', ['bar', 'baz']),
        ];

        // Nested array are not flattened like in the Eloquent builder. MongoDB can compare objects.
        $array = [['issue' => 45582], ['id' => 2], [3]];
        yield 'whereIn nested array' => [
            'find' => [['id' => ['$in' => $array]]],
            'aggregate' => [
                [
                    '$match' => ['id' => ['$in' => $array]],
                ],
            ],
            fn (Builder $builder) => $builder->whereIn('id', $array),
        ];

        yield 'orWhereIn' => [
            'find' => [
                [
                    '$or' => [
                        ['id' => 1],
                        ['id' => ['$in' => [1, 2, 3]]],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$or' => [
                            ['id' => 1],
                            ['id' => ['$in' => [1, 2, 3]]],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder->where('id', '=', 1)
                ->orWhereIn('id', [1, 2, 3]),
        ];

        /** @see DatabaseQueryBuilderTest::testBasicWhereNotIns */
        yield 'whereNotIn' => [
            'find' => [['id' => ['$nin' => [1, 2, 3]]]],
            'aggregate' => [
                [
                    '$match' => ['id' => ['$nin' => [1, 2, 3]]],
                ],
            ],
            fn (Builder $builder) => $builder->whereNotIn('id', [1, 2, 3]),
        ];

        yield 'orWhereNotIn' => [
            'find' => [
                [
                    '$or' => [
                        ['id' => 1],
                        ['id' => ['$nin' => [1, 2, 3]]],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$or' => [
                            ['id' => 1],
                            ['id' => ['$nin' => [1, 2, 3]]],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder->where('id', '=', 1)
                ->orWhereNotIn('id', [1, 2, 3]),
        ];

        /** @see DatabaseQueryBuilderTest::testEmptyWhereIns */
        yield 'whereIn empty array' => [
            'find' => [['id' => ['$in' => []]]],
            'aggregate' => [
                [
                    '$match' => ['id' => ['$in' => []]],
                ],
            ],
            fn (Builder $builder) => $builder->whereIn('id', []),
        ];

        yield 'find limit offset select' => [
            'find' => [[], ['skip' => 5, 'limit' => 10, 'projection' => ['foo' => true, 'bar' => true]]],
            'aggregate' => [
                ['$skip' => 5],
                ['$limit' => 10],
                ['$project' => ['foo' => true, 'bar' => true]],
            ],
            fn (Builder $builder) => $builder->limit(10)->offset(5)->select('foo', 'bar'),
        ];

        yield 'where accepts $ in operators' => [
            'find' => [
                [
                    '$or' => [
                        ['foo' => ['$type' => 2]],
                        ['foo' => ['$type' => 4]],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$or' => [
                            ['foo' => ['$type' => 2]],
                            ['foo' => ['$type' => 4]],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder
                ->where('foo', '$type', 2)
                ->orWhere('foo', '$type', 4),
        ];

        /** @see DatabaseQueryBuilderTest::testBasicWhereNot() */
        yield 'whereNot (multiple)' => [
            'find' => [
                [
                    '$and' => [
                        ['$nor' => [['name' => 'foo']]],
                        ['$nor' => [['name' => ['$ne' => 'bar']]]],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$and' => [
                            ['$nor' => [['name' => 'foo']]],
                            ['$nor' => [['name' => ['$ne' => 'bar']]]],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder
                ->whereNot('name', 'foo')
                ->whereNot('name', '<>', 'bar'),
        ];

        /** @see DatabaseQueryBuilderTest::testBasicOrWheres() */
        yield 'where orWhere' => [
            'find' => [
                [
                    '$or' => [
                        ['id' => 1],
                        ['email' => 'foo'],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$or' => [
                            ['id' => 1],
                            ['email' => 'foo'],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder
                ->where('id', '=', 1)
                ->orWhere('email', '=', 'foo'),
        ];

        /** @see DatabaseQueryBuilderTest::testBasicOrWhereNot() */
        yield 'orWhereNot' => [
            'find' => [
                [
                    '$or' => [
                        ['$nor' => [['name' => 'foo']]],
                        ['$nor' => [['name' => ['$ne' => 'bar']]]],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$or' => [
                            ['$nor' => [['name' => 'foo']]],
                            ['$nor' => [['name' => ['$ne' => 'bar']]]],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder
                ->orWhereNot('name', 'foo')
                ->orWhereNot('name', '<>', 'bar'),
        ];

        yield 'whereNot orWhere' => [
            'find' => [
                [
                    '$or' => [
                        ['$nor' => [['name' => 'foo']]],
                        ['name' => ['$ne' => 'bar']],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$or' => [
                            ['$nor' => [['name' => 'foo']]],
                            ['name' => ['$ne' => 'bar']],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder
                ->whereNot('name', 'foo')
                ->orWhere('name', '<>', 'bar'),
        ];

        /** @see DatabaseQueryBuilderTest::testWhereNot() */
        yield 'whereNot callable' => [
            'find' => [['$nor' => [['name' => 'foo']]]],
            'aggregate' => [
                [
                    '$match' => ['$nor' => [['name' => 'foo']]],
                ],
            ],
            fn (Builder $builder) => $builder
                ->whereNot(fn (Builder $q) => $q->where('name', 'foo')),
        ];

        yield 'where whereNot' => [
            'find' => [
                [
                    '$and' => [
                        ['name' => 'bar'],
                        ['$nor' => [['email' => 'foo']]],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$and' => [
                            ['name' => 'bar'],
                            ['$nor' => [['email' => 'foo']]],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder
                ->where('name', '=', 'bar')
                ->whereNot(function (Builder $q) {
                    $q->where('email', '=', 'foo');
                }),
        ];

        yield 'whereNot (nested)' => [
            'find' => [
                [
                    '$nor' => [
                        [
                            '$and' => [
                                ['name' => 'foo'],
                                ['$nor' => [['email' => ['$ne' => 'bar']]]],
                            ],
                        ],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$nor' => [
                            [
                                '$and' => [
                                    ['name' => 'foo'],
                                    ['$nor' => [['email' => ['$ne' => 'bar']]]],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder
                ->whereNot(function (Builder $q) {
                    $q->where('name', '=', 'foo')
                      ->whereNot('email', '<>', 'bar');
                }),
        ];

        yield 'orWhere orWhereNot' => [
            'find' => [
                [
                    '$or' => [
                        ['name' => 'bar'],
                        ['$nor' => [['email' => 'foo']]],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$or' => [
                            ['name' => 'bar'],
                            ['$nor' => [['email' => 'foo']]],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder
                ->orWhere('name', '=', 'bar')
                ->orWhereNot(function (Builder $q) {
                    $q->where('email', '=', 'foo');
                }),
        ];

        yield 'where orWhereNot' => [
            'find' => [
                [
                    '$or' => [
                        ['name' => 'bar'],
                        ['$nor' => [['email' => 'foo']]],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$or' => [
                            ['name' => 'bar'],
                            ['$nor' => [['email' => 'foo']]],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder
                ->where('name', '=', 'bar')
                ->orWhereNot('email', '=', 'foo'),
        ];

        /** @see DatabaseQueryBuilderTest::testWhereNotWithArrayConditions() */
        yield 'whereNot with arrays of single condition' => [
            'find' => [
                [
                    '$nor' => [
                        [
                            '$and' => [
                                ['foo' => 1],
                                ['bar' => 2],
                            ],
                        ],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$nor' => [
                            [
                                '$and' => [
                                    ['foo' => 1],
                                    ['bar' => 2],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder
                ->whereNot([['foo', 1], ['bar', 2]]),
        ];

        yield 'whereNot with single array of conditions' => [
            'find' => [
                [
                    '$nor' => [
                        [
                            '$and' => [
                                ['foo' => 1],
                                ['bar' => 2],
                            ],
                        ],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$nor' => [
                            [
                                '$and' => [
                                    ['foo' => 1],
                                    ['bar' => 2],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder
                ->whereNot(['foo' => 1, 'bar' => 2]),
        ];

        yield 'whereNot with arrays of single condition with operator' => [
            'find' => [
                [
                    '$nor' => [
                        [
                            '$and' => [
                                ['foo' => 1],
                                ['bar' => ['$lt' => 2]],
                            ],
                        ],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$nor' => [
                            [
                                '$and' => [
                                    ['foo' => 1],
                                    ['bar' => ['$lt' => 2]],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder
                ->whereNot([
                    ['foo', 1],
                    ['bar', '<', 2],
                ]),
        ];

        yield 'where all' => [
            'find' => [['tags' => ['$all' => ['ssl', 'security']]]],
            'aggregate' => [['$match' => ['tags' => ['$all' => ['ssl', 'security']]]]],
            fn (Builder $builder) => $builder->where('tags', 'all', ['ssl', 'security']),
        ];

        yield 'where all nested operators' => [
            'find' => [
                [
                    'tags' => [
                        '$all' => [
                            ['$elemMatch' => ['size' => 'M', 'num' => ['$gt' => 50]]],
                            ['$elemMatch' => ['num' => 100, 'color' => 'green']],
                        ],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        'tags' => [
                            '$all' => [
                                ['$elemMatch' => ['size' => 'M', 'num' => ['$gt' => 50]]],
                                ['$elemMatch' => ['num' => 100, 'color' => 'green']],
                            ],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder->where('tags', 'all', [
                ['$elemMatch' => ['size' => 'M', 'num' => ['$gt' => 50]]],
                ['$elemMatch' => ['num' => 100, 'color' => 'green']],
            ]),
        ];

        /** @see DatabaseQueryBuilderTest::testForPage() */
        yield 'forPage' => [
            'find' => [[], ['skip' => 40, 'limit' => 20]],
            'aggregate' => [
                ['$skip' => 40],
                ['$limit' => 20],
            ],
            fn (Builder $builder) => $builder->forPage(3, 20),
        ];

        /** @see DatabaseQueryBuilderTest::testLimitsAndOffsets() */
        yield 'offset limit' => [
            'find' => [[], ['skip' => 5, 'limit' => 10]],
            'aggregate' => [
                ['$skip' => 5],
                ['$limit' => 10],
            ],
            fn (Builder $builder) => $builder->offset(5)->limit(10),
        ];

        yield 'offset limit zero (unset)' => [
            'find' => [[]],
            'aggregate' => [],
            fn (Builder $builder) => $builder
                ->offset(0)->limit(0),
        ];

        yield 'offset limit zero (reset)' => [
            'find' => [[]],
            'aggregate' => [],
            fn (Builder $builder) => $builder
                ->offset(5)->limit(10)
                ->offset(0)->limit(0),
        ];

        yield 'offset limit negative (unset)' => [
            'find' => [[]],
            'aggregate' => [],
            fn (Builder $builder) => $builder
                ->offset(-5)->limit(-10),
        ];

        yield 'offset limit null (reset)' => [
            'find' => [[]],
            'aggregate' => [],
            fn (Builder $builder) => $builder
                ->offset(5)->limit(10)
                ->offset(null)->limit(null),
        ];

        yield 'skip take (aliases)' => [
            'find' => [[], ['skip' => 5, 'limit' => 10]],
            'aggregate' => [
                ['$skip' => 5],
                ['$limit' => 10],
            ],
            fn (Builder $builder) => $builder->skip(5)->limit(10),
        ];

        /** @see DatabaseQueryBuilderTest::testOrderBys() */
        yield 'orderBy multiple columns' => [
            'find' => [[], ['sort' => ['email' => 1, 'age' => -1]]],
            'aggregate' => [
                ['$sort' => ['email' => 1, 'age' => -1]],
            ],
            fn (Builder $builder) => $builder
                ->orderBy('email')
                ->orderBy('age', 'desc'),
        ];

        yield 'orders = null' => [
            'find' => [[]],
            'aggregate' => [],
            function (Builder $builder) {
                $builder->orders = null;

                return $builder;
            },
        ];

        yield 'orders = []' => [
            'find' => [[]],
            'aggregate' => [],
            function (Builder $builder) {
                $builder->orders = [];

                return $builder;
            },
        ];

        yield 'multiple orders with direction' => [
            'find' => [[], ['sort' => ['email' => -1, 'age' => 1]]],
            'aggregate' => [
                ['$sort' => ['email' => -1, 'age' => 1]],
            ],
            fn (Builder $builder) => $builder
                ->orderBy('email', -1)
                ->orderBy('age', 1),
        ];

        yield 'orderByDesc' => [
            'find' => [[], ['sort' => ['email' => -1]]],
            'aggregate' => [
                ['$sort' => ['email' => -1]],
            ],
            fn (Builder $builder) => $builder->orderByDesc('email'),
        ];

        /** @see DatabaseQueryBuilderTest::testReorder() */
        yield 'reorder reset' => [
            'find' => [[]],
            'aggregate' => [],
            fn (Builder $builder) => $builder->orderBy('name')->reorder(),
        ];

        yield 'reorder column' => [
            'find' => [[], ['sort' => ['name' => -1]]],
            'aggregate' => [
                ['$sort' => ['name' => -1]],
            ],
            fn (Builder $builder) => $builder->orderBy('name')->reorder('name', 'desc'),
        ];

        /** @link https://www.mongodb.com/docs/manual/reference/method/cursor.sort/#text-score-metadata-sort */
        yield 'orderBy array meta' => [
            'find' => [
                ['$text' => ['$search' => 'operating']],
                ['sort' => ['score' => ['$meta' => 'textScore']]],
            ],
            'aggregate' => [
                ['$match' => ['$text' => ['$search' => 'operating']]],
                ['$sort' => ['score' => ['$meta' => 'textScore']]],
            ],
            fn (Builder $builder) => $builder
                ->where('$text', ['$search' => 'operating'])
                ->orderBy('score', ['$meta' => 'textScore']),
        ];

        /** @see DatabaseQueryBuilderTest::testWhereBetweens() */
        yield 'whereBetween array of numbers' => [
            'find' => [['id' => ['$gte' => 1, '$lte' => 2]]],
            'aggregate' => [['$match' => ['id' => ['$gte' => 1, '$lte' => 2]]]],
            fn (Builder $builder) => $builder->whereBetween('id', [1, 2]),
        ];

        yield 'whereBetween nested array of numbers' => [
            'find' => [['id' => ['$gte' => [1], '$lte' => [2, 3]]]],
            'aggregate' => [['$match' => ['id' => ['$gte' => [1], '$lte' => [2, 3]]]]],
            fn (Builder $builder) => $builder->whereBetween('id', [[1], [2, 3]]),
        ];

        $period = now()->toPeriod(now()->addMonth());
        yield 'whereBetween CarbonPeriod' => [
            'find' => [
                [
                    'created_at' => [
                        '$gte' => new UTCDateTime($period->getStartDate()),
                        '$lte' => new UTCDateTime($period->getEndDate()),
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        'created_at' => [
                            '$gte' => new UTCDateTime($period->getStartDate()),
                            '$lte' => new UTCDateTime($period->getEndDate()),
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder->whereBetween('created_at', $period),
        ];

        yield 'whereBetween collection' => [
            'find' => [['id' => ['$gte' => 1, '$lte' => 2]]],
            'aggregate' => [['$match' => ['id' => ['$gte' => 1, '$lte' => 2]]]],
            fn (Builder $builder) => $builder->whereBetween('id', collect([1, 2])),
        ];

        /** @see DatabaseQueryBuilderTest::testOrWhereBetween() */
        yield 'orWhereBetween array of numbers' => [
            'find' => [
                [
                    '$or' => [
                        ['id' => 1],
                        ['id' => ['$gte' => 3, '$lte' => 5]],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$or' => [
                            ['id' => 1],
                            ['id' => ['$gte' => 3, '$lte' => 5]],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder
                ->where('id', '=', 1)
                ->orWhereBetween('id', [3, 5]),
        ];

        /** @link https://www.mongodb.com/docs/manual/reference/bson-type-comparison-order/#arrays */
        yield 'orWhereBetween nested array of numbers' => [
            'find' => [
                [
                    '$or' => [
                        ['id' => 1],
                        ['id' => ['$gte' => [4], '$lte' => [6, 8]]],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$or' => [
                            ['id' => 1],
                            ['id' => ['$gte' => [4], '$lte' => [6, 8]]],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder
                ->where('id', '=', 1)
                ->orWhereBetween('id', [[4], [6, 8]]),
        ];

        yield 'orWhereBetween collection' => [
            'find' => [
                [
                    '$or' => [
                        ['id' => 1],
                        ['id' => ['$gte' => 3, '$lte' => 4]],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$or' => [
                            ['id' => 1],
                            ['id' => ['$gte' => 3, '$lte' => 4]],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder
                ->where('id', '=', 1)
                ->orWhereBetween('id', collect([3, 4])),
        ];

        yield 'whereNotBetween array of numbers' => [
            'find' => [
                [
                    '$or' => [
                        ['id' => ['$lte' => 1]],
                        ['id' => ['$gte' => 2]],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$or' => [
                            ['id' => ['$lte' => 1]],
                            ['id' => ['$gte' => 2]],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder->whereNotBetween('id', [1, 2]),
        ];

        /** @see DatabaseQueryBuilderTest::testOrWhereNotBetween() */
        yield 'orWhereNotBetween array of numbers' => [
            'find' => [
                [
                    '$or' => [
                        ['id' => 1],
                        [
                            '$or' => [
                                ['id' => ['$lte' => 3]],
                                ['id' => ['$gte' => 5]],
                            ],
                        ],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$or' => [
                            ['id' => 1],
                            [
                                '$or' => [
                                    ['id' => ['$lte' => 3]],
                                    ['id' => ['$gte' => 5]],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder
                ->where('id', '=', 1)
                ->orWhereNotBetween('id', [3, 5]),
        ];

        yield 'orWhereNotBetween nested array of numbers' => [
            'find' => [
                [
                    '$or' => [
                        ['id' => 1],
                        [
                            '$or' => [
                                ['id' => ['$lte' => [2, 3]]],
                                ['id' => ['$gte' => [5]]],
                            ],
                        ],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$or' => [
                            ['id' => 1],
                            [
                                '$or' => [
                                    ['id' => ['$lte' => [2, 3]]],
                                    ['id' => ['$gte' => [5]]],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder
                ->where('id', '=', 1)
                ->orWhereNotBetween('id', [[2, 3], [5]]),
        ];

        yield 'orWhereNotBetween collection' => [
            'find' => [
                [
                    '$or' => [
                        ['id' => 1],
                        [
                            '$or' => [
                                ['id' => ['$lte' => 3]],
                                ['id' => ['$gte' => 4]],
                            ],
                        ],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$or' => [
                            ['id' => 1],
                            [
                                '$or' => [
                                    ['id' => ['$lte' => 3]],
                                    ['id' => ['$gte' => 4]],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder
                ->where('id', '=', 1)
                ->orWhereNotBetween('id', collect([3, 4])),
        ];

        yield 'where like' => [
            'find' => [['name' => new Regex('^acme$', 'i')]],
            'aggregate' => [['$match' => ['name' => new Regex('^acme$', 'i')]]],
            fn (Builder $builder) => $builder->where('name', 'like', 'acme'),
        ];

        yield 'where ilike' => [ // Alias for like
            'find' => [['name' => new Regex('^acme$', 'i')]],
            [['$match' => ['name' => new Regex('^acme$', 'i')]]],
            fn (Builder $builder) => $builder->where('name', 'ilike', 'acme'),
        ];

        yield 'where like escape' => [
            'find' => [['name' => new Regex('^\^ac\.me\$$', 'i')]],
            'aggregate' => [['$match' => ['name' => new Regex('^\^ac\.me\$$', 'i')]]],
            fn (Builder $builder) => $builder->where('name', 'like', '^ac.me$'),
        ];

        yield 'where like unescaped \% \_' => [
            'find' => [['name' => new Regex('^a%cm_e$', 'i')]],
            'aggregate' => [['$match' => ['name' => new Regex('^a%cm_e$', 'i')]]],
            fn (Builder $builder) => $builder->where('name', 'like', 'a\%cm\_e'),
        ];

        yield 'where like %' => [
            'find' => [['name' => new Regex('^.*ac.*me.*$', 'i')]],
            'aggregate' => [['$match' => ['name' => new Regex('^.*ac.*me.*$', 'i')]]],
            fn (Builder $builder) => $builder->where('name', 'like', '%ac%%me%'),
        ];

        yield 'where like _' => [
            'find' => [['name' => new Regex('^.ac..me.$', 'i')]],
            'aggregate' => [['$match' => ['name' => new Regex('^.ac..me.$', 'i')]]],
            fn (Builder $builder) => $builder->where('name', 'like', '_ac__me_'),
        ];

        $regex = new Regex('^acme$', 'si');
        yield 'where BSON\Regex' => [
            'find' => [['name' => $regex]],
            'aggregate' => [['$match' => ['name' => $regex]]],
            fn (Builder $builder) => $builder->where('name', 'regex', $regex),
        ];

        yield 'where regexp' => [ // Alias for regex
            'find' => [['name' => $regex]],
            [['$match' => ['name' => $regex]]],
            fn (Builder $builder) => $builder->where('name', 'regex', '/^acme$/si'),
        ];

        yield 'where regex delimiter /' => [
            'find' => [['name' => $regex]],
            'aggregate' => [['$match' => ['name' => $regex]]],
            fn (Builder $builder) => $builder->where('name', 'regex', '/^acme$/si'),
        ];

        yield 'where regex delimiter #' => [
            'find' => [['name' => $regex]],
            'aggregate' => [['$match' => ['name' => $regex]]],
            fn (Builder $builder) => $builder->where('name', 'regex', '#^acme$#si'),
        ];

        yield 'where regex delimiter ~' => [
            'find' => [['name' => $regex]],
            'aggregate' => [['$match' => ['name' => $regex]]],
            fn (Builder $builder) => $builder->where('name', 'regex', '#^acme$#si'),
        ];

        yield 'where regex with escaped characters' => [
            'find' => [['name' => new Regex('a\.c\/m\+e', '')]],
            'aggregate' => [['$match' => ['name' => new Regex('a\.c\/m\+e', '')]]],
            fn (Builder $builder) => $builder->where('name', 'regex', '/a\.c\/m\+e/'),
        ];

        yield 'where not regex' => [
            'find' => [['name' => ['$not' => $regex]]],
            'aggregate' => [['$match' => ['name' => ['$not' => $regex]]]],
            fn (Builder $builder) => $builder->where('name', 'not regex', '/^acme$/si'),
        ];

        yield 'where date' => [
            'find' => [
                [
                    'created_at' => [
                        '$gte' => new UTCDateTime(new DateTimeImmutable('2018-09-30 00:00:00.000 +00:00')),
                        '$lte' => new UTCDateTime(new DateTimeImmutable('2018-09-30 23:59:59.999 +00:00')),
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        'created_at' => [
                            '$gte' => new UTCDateTime(new DateTimeImmutable('2018-09-30 00:00:00.000 +00:00')),
                            '$lte' => new UTCDateTime(new DateTimeImmutable('2018-09-30 23:59:59.999 +00:00')),
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder->whereDate('created_at', '2018-09-30'),
        ];

        yield 'where date DateTimeImmutable' => [
            'find' => [
                [
                    'created_at' => [
                        '$gte' => new UTCDateTime(new DateTimeImmutable('2018-09-30 00:00:00.000 +00:00')),
                        '$lte' => new UTCDateTime(new DateTimeImmutable('2018-09-30 23:59:59.999 +00:00')),
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        'created_at' => [
                            '$gte' => new UTCDateTime(new DateTimeImmutable('2018-09-30 00:00:00.000 +00:00')),
                            '$lte' => new UTCDateTime(new DateTimeImmutable('2018-09-30 23:59:59.999 +00:00')),
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder->whereDate('created_at', '=', new DateTimeImmutable('2018-09-30 15:00:00 +02:00')),
        ];

        yield 'where date !=' => [
            'find' => [
                [
                    'created_at' => [
                        '$not' => [
                            '$gte' => new UTCDateTime(new DateTimeImmutable('2018-09-30 00:00:00.000 +00:00')),
                            '$lte' => new UTCDateTime(new DateTimeImmutable('2018-09-30 23:59:59.999 +00:00')),
                        ],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        'created_at' => [
                            '$not' => [
                                '$gte' => new UTCDateTime(new DateTimeImmutable('2018-09-30 00:00:00.000 +00:00')),
                                '$lte' => new UTCDateTime(new DateTimeImmutable('2018-09-30 23:59:59.999 +00:00')),
                            ],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder->whereDate('created_at', '!=', '2018-09-30'),
        ];

        yield 'where date <' => [
            'find' => [
                [
                    'created_at' => [
                        '$lt' => new UTCDateTime(new DateTimeImmutable('2018-09-30 00:00:00.000 +00:00')),
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        'created_at' => [
                            '$lt' => new UTCDateTime(new DateTimeImmutable('2018-09-30 00:00:00.000 +00:00')),
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder->whereDate('created_at', '<', '2018-09-30'),
        ];

        yield 'where date >=' => [
            'find' => [
                [
                    'created_at' => [
                        '$gte' => new UTCDateTime(new DateTimeImmutable('2018-09-30 00:00:00.000 +00:00')),
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        'created_at' => [
                            '$gte' => new UTCDateTime(new DateTimeImmutable('2018-09-30 00:00:00.000 +00:00')),
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder->whereDate('created_at', '>=', '2018-09-30'),
        ];

        yield 'where date >' => [
            'find' => [
                [
                    'created_at' => [
                        '$gt' => new UTCDateTime(new DateTimeImmutable('2018-09-30 23:59:59.999 +00:00')),
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        'created_at' => [
                            '$gt' => new UTCDateTime(new DateTimeImmutable('2018-09-30 23:59:59.999 +00:00')),
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder->whereDate('created_at', '>', '2018-09-30'),
        ];

        yield 'where date <=' => [
            'find' => [
                [
                    'created_at' => [
                        '$lte' => new UTCDateTime(new DateTimeImmutable('2018-09-30 23:59:59.999 +00:00')),
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        'created_at' => [
                            '$lte' => new UTCDateTime(new DateTimeImmutable('2018-09-30 23:59:59.999 +00:00')),
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder->whereDate('created_at', '<=', '2018-09-30'),
        ];

        yield 'where day' => [
            'find' => [
                [
                    '$expr' => [
                        '$eq' => [
                            ['$dayOfMonth' => '$created_at'],
                            5,
                        ],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$expr' => [
                            '$eq' => [
                                ['$dayOfMonth' => '$created_at'],
                                5,
                            ],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder->whereDay('created_at', 5),
        ];

        yield 'where day > string' => [
            'find' => [
                [
                    '$expr' => [
                        '$gt' => [
                            ['$dayOfMonth' => '$created_at'],
                            5,
                        ],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$expr' => [
                            '$gt' => [
                                ['$dayOfMonth' => '$created_at'],
                                5,
                            ],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder->whereDay('created_at', '>', '05'),
        ];

        yield 'where month' => [
            'find' => [
                [
                    '$expr' => [
                        '$eq' => [
                            ['$month' => '$created_at'],
                            10,
                        ],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$expr' => [
                            '$eq' => [
                                ['$month' => '$created_at'],
                                10,
                            ],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder->whereMonth('created_at', 10),
        ];

        yield 'where month > string' => [
            'find' => [
                [
                    '$expr' => [
                        '$gt' => [
                            ['$month' => '$created_at'],
                            5,
                        ],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$expr' => [
                            '$gt' => [
                                ['$month' => '$created_at'],
                                5,
                            ],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder->whereMonth('created_at', '>', '05'),
        ];

        yield 'where year' => [
            'find' => [
                [
                    '$expr' => [
                        '$eq' => [
                            ['$year' => '$created_at'],
                            2023,
                        ],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$expr' => [
                            '$eq' => [
                                ['$year' => '$created_at'],
                                2023,
                            ],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder->whereYear('created_at', 2023),
        ];

        yield 'where year > string' => [
            'find' => [
                [
                    '$expr' => [
                        '$gt' => [
                            ['$year' => '$created_at'],
                            2023,
                        ],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$expr' => [
                            '$gt' => [
                                ['$year' => '$created_at'],
                                2023,
                            ],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder->whereYear('created_at', '>', '2023'),
        ];

        yield 'where time HH:MM:SS' => [
            'find' => [
                [
                    '$expr' => [
                        '$eq' => [
                            ['$dateToString' => ['date' => '$created_at', 'format' => '%H:%M:%S']],
                            '10:11:12',
                        ],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$expr' => [
                            '$eq' => [
                                ['$dateToString' => ['date' => '$created_at', 'format' => '%H:%M:%S']],
                                '10:11:12',
                            ],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder->whereTime('created_at', '10:11:12'),
        ];

        yield 'where time HH:MM' => [
            'find' => [
                [
                    '$expr' => [
                        '$eq' => [
                            ['$dateToString' => ['date' => '$created_at', 'format' => '%H:%M']],
                            '10:11',
                        ],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$expr' => [
                            '$eq' => [
                                ['$dateToString' => ['date' => '$created_at', 'format' => '%H:%M']],
                                '10:11',
                            ],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder->whereTime('created_at', '10:11'),
        ];

        yield 'where time HH' => [
            'find' => [
                [
                    '$expr' => [
                        '$eq' => [
                            ['$dateToString' => ['date' => '$created_at', 'format' => '%H']],
                            '10',
                        ],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$expr' => [
                            '$eq' => [
                                ['$dateToString' => ['date' => '$created_at', 'format' => '%H']],
                                '10',
                            ],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder->whereTime('created_at', '10'),
        ];

        yield 'where time DateTime' => [
            'find' => [
                [
                    '$expr' => [
                        '$eq' => [
                            ['$dateToString' => ['date' => '$created_at', 'format' => '%H:%M:%S']],
                            '10:11:12',
                        ],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$expr' => [
                            '$eq' => [
                                ['$dateToString' => ['date' => '$created_at', 'format' => '%H:%M:%S']],
                                '10:11:12',
                            ],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder->whereTime('created_at', new DateTimeImmutable('2023-08-22 10:11:12')),
        ];

        yield 'where time >' => [
            'find' => [
                [
                    '$expr' => [
                        '$gt' => [
                            ['$dateToString' => ['date' => '$created_at', 'format' => '%H:%M:%S']],
                            '10:11:12',
                        ],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        '$expr' => [
                            '$gt' => [
                                ['$dateToString' => ['date' => '$created_at', 'format' => '%H:%M:%S']],
                                '10:11:12',
                            ],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder->whereTime('created_at', '>', '10:11:12'),
        ];

        /** @see DatabaseQueryBuilderTest::testBasicSelectDistinct */
        /** @todo implement distinct using agg pipeline
        yield 'distinct' => [
           'find  => [],
         * 'aggregate' => ['distinct' => ['foo', [], []]],
            fn (Builder $builder) => $builder->distinct('foo'),
        ];

        yield 'select distinct' => [
           'find  => [],
         * 'aggregate' => ['distinct' => ['foo', [], []]],
            fn (Builder $builder) => $builder->select('foo', 'bar')
                ->distinct(),
        ];

        yield 'select distinct on' => [
           'find  => [],
         * 'aggregate' => ['distinct' => ['foo', [], []]],
            fn (Builder $builder) => $builder->distinct('foo')
                ->select('foo', 'bar'),
        ];
        */

        /** @see DatabaseQueryBuilderTest::testLatest() */
        yield 'latest' => [
            'find' => [[], ['sort' => ['created_at' => -1]]],
            'aggregate' => [
                ['$sort' => ['created_at' => -1]],
            ],
            fn (Builder $builder) => $builder->latest(),
        ];

        yield 'latest limit' => [
            'find' => [[], ['sort' => ['created_at' => -1], 'limit' => 1]],
            'aggregate' => [
                ['$sort' => ['created_at' => -1]],
                ['$limit' => 1],
            ],
            fn (Builder $builder) => $builder->latest()->limit(1),
        ];

        yield 'latest custom field' => [
            'find' => [[], ['sort' => ['updated_at' => -1]]],
            'aggregate' => [
                ['$sort' => ['updated_at' => -1]],
            ],
            fn (Builder $builder) => $builder->latest('updated_at'),
        ];

        /** @see DatabaseQueryBuilderTest::testOldest() */
        yield 'oldest' => [
            'find' => [[], ['sort' => ['created_at' => 1]]],
            'aggregate' => [
                ['$sort' => ['created_at' => 1]],
            ],
            fn (Builder $builder) => $builder->oldest(),
        ];

        yield 'oldest limit' => [
            'find' => [[], ['sort' => ['created_at' => 1], 'limit' => 1]],
            'aggregate' => [
                ['$sort' => ['created_at' => 1]],
                ['$limit' => 1],
            ],
            fn (Builder $builder) => $builder->oldest()->limit(1),
        ];

        yield 'oldest custom field' => [
            'find' => [[], ['sort' => ['updated_at' => 1]]],
            'aggregate' => [
                ['$sort' => ['updated_at' => 1]],
            ],
            fn (Builder $builder) => $builder->oldest('updated_at'),
        ];

        yield 'sub-query' => [
            'find' => [
                [
                    'filters' => [
                        '$elemMatch' => [
                            '$and' => [
                                ['search_by' => 'by search'],
                                ['value' => 'foo'],
                            ],
                        ],
                    ],
                ],
            ],
            'aggregate' => [
                [
                    '$match' => [
                        'filters' => [
                            '$elemMatch' => [
                                '$and' => [
                                    ['search_by' => 'by search'],
                                    ['value' => 'foo'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            fn (Builder $builder) => $builder->where(
                'filters',
                'elemMatch',
                function (Builder $elemMatchQuery): void {
                    $elemMatchQuery->where([ 'search_by' => 'by search', 'value' => 'foo' ]);
                },
            ),
        ];

        // Method added in Laravel v10.47.0
        if (method_exists(Builder::class, 'whereAll')) {
            /** @see DatabaseQueryBuilderTest::testWhereAll */
            yield 'whereAll' => [
                [
                    'find' => [
                        ['$and' => [['last_name' => 'Doe'], ['email' => 'Doe']]],
                        [], // options
                    ],
                ],
                fn(Builder $builder) => $builder->whereAll(['last_name', 'email'], 'Doe'),
            ];

            yield 'whereAll operator' => [
                [
                    'find' => [
                        [
                            '$and' => [
                                ['last_name' => ['$not' => new Regex('^.*Doe.*$', 'i')]],
                                ['email' => ['$not' => new Regex('^.*Doe.*$', 'i')]],
                            ],
                        ],
                        [], // options
                    ],
                ],
                fn(Builder $builder) => $builder->whereAll(['last_name', 'email'], 'not like', '%Doe%'),
            ];

            /** @see DatabaseQueryBuilderTest::testOrWhereAll */
            yield 'orWhereAll' => [
                [
                    'find' => [
                        [
                            '$or' => [
                                ['first_name' => 'John'],
                                ['$and' => [['last_name' => 'Doe'], ['email' => 'Doe']]],
                            ],
                        ],
                        [], // options
                    ],
                ],
                fn(Builder $builder) => $builder
                    ->where('first_name', 'John')
                    ->orWhereAll(['last_name', 'email'], 'Doe'),
            ];

            yield 'orWhereAll operator' => [
                [
                    'find' => [
                        [
                            '$or' => [
                                ['first_name' => new Regex('^.*John.*$', 'i')],
                                [
                                    '$and' => [
                                        ['last_name' => ['$not' => new Regex('^.*Doe.*$', 'i')]],
                                        ['email' => ['$not' => new Regex('^.*Doe.*$', 'i')]],
                                    ],
                                ],
                            ],
                        ],
                        [], // options
                    ],
                ],
                fn(Builder $builder) => $builder
                    ->where('first_name', 'like', '%John%')
                    ->orWhereAll(['last_name', 'email'], 'not like', '%Doe%'),
            ];
        }

        // Method added in Laravel v10.47.0
        if (method_exists(Builder::class, 'whereAny')) {
            /** @see DatabaseQueryBuilderTest::testWhereAny */
            yield 'whereAny' => [
                [
                    'find' => [
                        ['$or' => [['last_name' => 'Doe'], ['email' => 'Doe']]],
                        [], // options
                    ],
                ],
                fn(Builder $builder) => $builder->whereAny(['last_name', 'email'], 'Doe'),
            ];

            yield 'whereAny operator' => [
                [
                    'find' => [
                        [
                            '$or' => [
                                ['last_name' => ['$not' => new Regex('^.*Doe.*$', 'i')]],
                                ['email' => ['$not' => new Regex('^.*Doe.*$', 'i')]],
                            ],
                        ],
                        [], // options
                    ],
                ],
                fn(Builder $builder) => $builder->whereAny(['last_name', 'email'], 'not like', '%Doe%'),
            ];

            /** @see DatabaseQueryBuilderTest::testOrWhereAny */
            yield 'orWhereAny' => [
                [
                    'find' => [
                        [
                            '$or' => [
                                ['first_name' => 'John'],
                                ['$or' => [['last_name' => 'Doe'], ['email' => 'Doe']]],
                            ],
                        ],
                        [], // options
                    ],
                ],
                fn(Builder $builder) => $builder
                    ->where('first_name', 'John')
                    ->orWhereAny(['last_name', 'email'], 'Doe'),
            ];

            yield 'orWhereAny operator' => [
                [
                    'find' => [
                        [
                            '$or' => [
                                ['first_name' => new Regex('^.*John.*$', 'i')],
                                [
                                    '$or' => [
                                        ['last_name' => ['$not' => new Regex('^.*Doe.*$', 'i')]],
                                        ['email' => ['$not' => new Regex('^.*Doe.*$', 'i')]],
                                    ],
                                ],
                            ],
                        ],
                        [], // options
                    ],
                ],
                fn(Builder $builder) => $builder
                    ->where('first_name', 'like', '%John%')
                    ->orWhereAny(['last_name', 'email'], 'not like', '%Doe%'),
            ];
        }
    }

    /** @dataProvider provideExceptions */
    public function testException($class, $message, Closure $build): void
    {
        $builder = self::getBuilder();

        $this->expectException($class);
        $this->expectExceptionMessage($message);
        $build($builder)->toMQL();
    }

    public static function provideExceptions(): iterable
    {
        yield 'orderBy invalid direction' => [
            InvalidArgumentException::class,
            'Order direction must be "asc" or "desc"',
            fn (Builder $builder) => $builder->orderBy('_id', 'dasc'),
        ];

        /** @see DatabaseQueryBuilderTest::testWhereBetweens */
        yield 'whereBetween array too short' => [
            InvalidArgumentException::class,
            'Between $values must be a list with exactly two elements: [min, max]',
            fn (Builder $builder) => $builder->whereBetween('id', [1]),
        ];

        yield 'whereBetween array too short (nested)' => [
            InvalidArgumentException::class,
            'Between $values must be a list with exactly two elements: [min, max]',
            fn (Builder $builder) => $builder->whereBetween('id', [[1, 2]]),
        ];

        yield 'whereBetween array too long' => [
            InvalidArgumentException::class,
            'Between $values must be a list with exactly two elements: [min, max]',
            fn (Builder $builder) => $builder->whereBetween('id', [1, 2, 3]),
        ];

        yield 'whereBetween collection too long' => [
            InvalidArgumentException::class,
            'Between $values must be a list with exactly two elements: [min, max]',
            fn (Builder $builder) => $builder->whereBetween('id', new Collection([1, 2, 3])),
        ];

        yield 'whereBetween array is not a list' => [
            InvalidArgumentException::class,
            'Between $values must be a list with exactly two elements: [min, max]',
            fn (Builder $builder) => $builder->whereBetween('id', ['min' => 1, 'max' => 2]),
        ];

        yield 'find with single string argument' => [
            ArgumentCountError::class,
            'Too few arguments to function MongoDB\Laravel\Query\Builder::where(\'foo\'), 1 passed and at least 2 expected when the 1st is not an array',
            fn (Builder $builder) => $builder->where('foo'),
        ];

        yield 'find with single numeric argument' => [
            ArgumentCountError::class,
            'Too few arguments to function MongoDB\Laravel\Query\Builder::where(123), 1 passed and at least 2 expected when the 1st is not an array',
            fn (Builder $builder) => $builder->where(123),
        ];

        yield 'where regex not starting with /' => [
            LogicException::class,
            'Missing expected starting delimiter in regular expression "^ac/me$", supported delimiters are: / # ~',
            fn (Builder $builder) => $builder->where('name', 'regex', '^ac/me$'),
        ];

        yield 'where regex not ending with /' => [
            LogicException::class,
            'Missing expected ending delimiter "/" in regular expression "/foo#bar"',
            fn (Builder $builder) => $builder->where('name', 'regex', '/foo#bar'),
        ];

        yield 'whereTime with invalid time' => [
            InvalidArgumentException::class,
            'Invalid time format, expected HH:MM:SS, HH:MM or HH, got "10:11:12:13"',
            fn (Builder $builder) => $builder->whereTime('created_at', '10:11:12:13'),
        ];

        yield 'whereTime out of range' => [
            InvalidArgumentException::class,
            'Invalid time format, expected HH:MM:SS, HH:MM or HH, got "23:70"',
            fn (Builder $builder) => $builder->whereTime('created_at', '23:70'),
        ];

        yield 'whereTime invalid type' => [
            InvalidArgumentException::class,
            'Invalid time format, expected HH:MM:SS, HH:MM or HH, got "stdClass"',
            fn (Builder $builder) => $builder->whereTime('created_at', new stdClass()),
        ];

        yield 'where invalid column type' => [
            InvalidArgumentException::class,
            'First argument of MongoDB\Laravel\Query\Builder::where must be a field path as "string". Got "float"',
            fn (Builder $builder) => $builder->where(2.3, '>', 1),
        ];
    }

    /** @dataProvider getEloquentMethodsNotSupported */
    public function testEloquentMethodsNotSupported(Closure $callback)
    {
        $builder = self::getBuilder();

        $this->expectException(BadMethodCallException::class);
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

        /** @see DatabaseQueryBuilderTest::testWhereIntegerInRaw */
        yield 'whereIntegerInRaw' => [fn (Builder $builder) => $builder->whereIntegerInRaw('id', ['1a', 2])];

        /** @see DatabaseQueryBuilderTest::testOrWhereIntegerInRaw */
        yield 'orWhereIntegerInRaw' => [fn (Builder $builder) => $builder->orWhereIntegerInRaw('id', ['1a', 2])];

        /** @see DatabaseQueryBuilderTest::testWhereIntegerNotInRaw */
        yield 'whereIntegerNotInRaw' => [fn (Builder $builder) => $builder->whereIntegerNotInRaw('id', ['1a', 2])];

        /** @see DatabaseQueryBuilderTest::testOrWhereIntegerNotInRaw */
        yield 'orWhereIntegerNotInRaw' => [fn (Builder $builder) => $builder->orWhereIntegerNotInRaw('id', ['1a', 2])];
    }

    private static function getBuilder(): Builder
    {
        $connection = m::mock(Connection::class);
        $processor  = m::mock(Processor::class);
        $collection = m::mock(\MongoDB\Laravel\Collection::class);
        $connection->shouldReceive('getSession')->andReturn(null);
        $connection->shouldReceive('getQueryGrammar')->andReturn(new Grammar());
        $connection->shouldReceive('getCollection')->andReturn($collection);

        $builder = new Builder($connection, null, $processor);
        $builder->from('users');

        return $builder;
    }
}
