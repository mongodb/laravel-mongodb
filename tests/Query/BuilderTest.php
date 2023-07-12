<?php

declare(strict_types=1);

namespace Jenssegers\Mongodb\Tests\Query;

use DateTimeImmutable;
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

        yield 'distinct' => [
            ['distinct' => ['foo', [], []]],
            fn (Builder $builder) => $builder->distinct('foo'),
        ];

        yield 'groupBy' => [
            ['aggregate' => [[['$group' => ['_id' => ['foo' => '$foo'], 'foo' => ['$last' => '$foo']]]], []]],
            fn (Builder $builder) => $builder->groupBy('foo'),
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
