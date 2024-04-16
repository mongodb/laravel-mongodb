<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use DateTimeImmutable;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Builder\Accumulator;
use MongoDB\Builder\Expression;
use MongoDB\Builder\Query;
use MongoDB\Builder\Type\Sort;
use MongoDB\Laravel\Tests\Models\User;
use MongoDB\Laravel\Tests\TestCase;

use function print_r;

class AggregationsBuilderTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testAggregationBuilderMatchStage(): void
    {
        User::truncate();
        // begin aggregation builder sample data
        User::insert([
            ['name' => 'Alda Gröndal', 'occupation' => 'engineer', 'birthday' => new UTCDateTime(new DateTimeImmutable('2002-01-01'))],
            ['name' => 'Francois Soma', 'occupation' => 'engineer', 'birthday' => new UTCDateTime(new DateTimeImmutable('1998-02-02'))],
            ['name' => 'Janet Doe', 'occupation' => 'designer', 'birthday' => new UTCDateTime(new DateTimeImmutable('1987-03-03'))],
            ['name' => 'Eliud Nkosana', 'occupation' => 'engineer', 'birthday' => new UTCDateTime(new DateTimeImmutable('1984-04-04'))],
            ['name' => 'Bran Steafan', 'occupation' => 'engineer', 'birthday' => new UTCDateTime(new DateTimeImmutable('1998-05-05'))],
            ['name' => 'Ellis Lee', 'occupation' => 'designer', 'birthday' => new UTCDateTime(new DateTimeImmutable('1996-06-06'))],
        ]);
        // end aggregation builder sample data

        // begin aggregation match stage
        $pipeline = User::aggregate()
            ->match(Query::or(
                Query::query(occupation: Query::eq('designer')),
                Query::query(name: Query::eq('Eliud Nkosana')),
            ));
        $result = $pipeline->get();
        // end aggregation match stage

        print_r($result->toJson());
        $this->assertEquals(3, $result->count());
    }

    public function testAggregationBuilderGroupStage(): void
    {
        User::truncate();
        User::insert([
            ['name' => 'Alda Gröndal', 'occupation' => 'engineer', 'birthday' => new UTCDateTime(new DateTimeImmutable('2002-01-01'))],
            ['name' => 'Francois Soma', 'occupation' => 'engineer', 'birthday' => new UTCDateTime(new DateTimeImmutable('1998-02-02'))],
            ['name' => 'Janet Doe', 'occupation' => 'designer', 'birthday' => new UTCDateTime(new DateTimeImmutable('1987-03-03'))],
            ['name' => 'Eliud Nkosana', 'occupation' => 'engineer', 'birthday' => new UTCDateTime(new DateTimeImmutable('1984-04-04'))],
            ['name' => 'Bran Steafan', 'occupation' => 'engineer', 'birthday' => new UTCDateTime(new DateTimeImmutable('1998-05-05'))],
            ['name' => 'Ellis Lee', 'occupation' => 'designer', 'birthday' => new UTCDateTime(new DateTimeImmutable('1996-06-06'))],
        ]);

        // begin aggregation group stage
        $pipeline = User::aggregate()
            ->group(_id: Expression::fieldPath('occupation'));
        $result = $pipeline->get();
        // end aggregation group stage

        print_r($result->toJson());
        $this->assertEquals(2, $result->count());
    }

    public function testAggregationBuilderSortStage(): void
    {
        User::truncate();
        User::insert([
            ['name' => 'Alda Gröndal', 'occupation' => 'engineer', 'birthday' => new UTCDateTime(new DateTimeImmutable('2002-01-01'))],
            ['name' => 'Francois Soma', 'occupation' => 'engineer', 'birthday' => new UTCDateTime(new DateTimeImmutable('1998-02-02'))],
            ['name' => 'Janet Doe', 'occupation' => 'designer', 'birthday' => new UTCDateTime(new DateTimeImmutable('1987-03-03'))],
            ['name' => 'Eliud Nkosana', 'occupation' => 'engineer', 'birthday' => new UTCDateTime(new DateTimeImmutable('1984-04-04'))],
            ['name' => 'Bran Steafan', 'occupation' => 'engineer', 'birthday' => new UTCDateTime(new DateTimeImmutable('1998-05-05'))],
            ['name' => 'Ellis Lee', 'occupation' => 'designer', 'birthday' => new UTCDateTime(new DateTimeImmutable('1996-06-06'))],
        ]);

        // begin aggregation sort stage
        $pipeline = User::aggregate()
            ->sort(name: Sort::Desc);
        $result = $pipeline->get();
        // end aggregation sort stage

        $this->assertEquals(6, $result->count());
        $this->assertEquals('Janet Doe', $result->first()['name']);
    }

    public function testAggregationBuilderProjectStage(): void
    {
        User::truncate();
        User::insert([
            ['name' => 'Alda Gröndal', 'occupation' => 'engineer', 'birthday' => new UTCDateTime(new DateTimeImmutable('2002-01-01'))],
            ['name' => 'Francois Soma', 'occupation' => 'engineer', 'birthday' => new UTCDateTime(new DateTimeImmutable('1998-02-02'))],
            ['name' => 'Janet Doe', 'occupation' => 'designer', 'birthday' => new UTCDateTime(new DateTimeImmutable('1987-03-03'))],
            ['name' => 'Eliud Nkosana', 'occupation' => 'engineer', 'birthday' => new UTCDateTime(new DateTimeImmutable('1984-04-04'))],
            ['name' => 'Bran Steafan', 'occupation' => 'engineer', 'birthday' => new UTCDateTime(new DateTimeImmutable('1998-05-05'))],
            ['name' => 'Ellis Lee', 'occupation' => 'designer', 'birthday' => new UTCDateTime(new DateTimeImmutable('1996-06-06'))],
        ]);

        // begin aggregation project stage
        $pipeline = User::aggregate()
            ->project(_id: 0, name: 1);
        $result = $pipeline->get();
        // end aggregation project stage

        $this->assertEquals(6, $result->count());
        $this->assertNotNull($result->first()['name']);
        $this->assertArrayNotHasKey('_id', $result->first());
    }

    public function testAggregationBuilderPipeline(): void
    {
        User::truncate();
        User::insert([
            ['name' => 'Alda Gröndal', 'occupation' => 'engineer', 'birthday' => new UTCDateTime(new DateTimeImmutable('2002-01-01'))],
            ['name' => 'Francois Soma', 'occupation' => 'engineer', 'birthday' => new UTCDateTime(new DateTimeImmutable('1998-02-02'))],
            ['name' => 'Janet Doe', 'occupation' => 'designer', 'birthday' => new UTCDateTime(new DateTimeImmutable('1987-03-03'))],
            ['name' => 'Eliud Nkosana', 'occupation' => 'engineer', 'birthday' => new UTCDateTime(new DateTimeImmutable('1984-04-04'))],
            ['name' => 'Bran Steafan', 'occupation' => 'engineer', 'birthday' => new UTCDateTime(new DateTimeImmutable('1998-05-05'))],
            ['name' => 'Ellis Lee', 'occupation' => 'designer', 'birthday' => new UTCDateTime(new DateTimeImmutable('1996-06-06'))],
        ]);

        // begin pipeline example
        $pipeline = User::aggregate()
            ->addFields(
                year: Expression::year(
                    Expression::dateFieldPath('birthday'),
                ),
            )
            ->group(
                _id: Expression::fieldPath('occupation'),
                year_avg: Accumulator::avg(Expression::numberFieldPath('year')),
            )
            ->sort(_id: Sort::Asc)
            ->project(profession: Expression::fieldPath('_id'), year_avg: 1, _id: 0);
        // end pipeline example

        $result = $pipeline->get();
        print_r($result->toJson());
        $this->assertEquals(2, $result->count());
        $this->assertNotNull($result->first()['year_avg']);
    }
}
