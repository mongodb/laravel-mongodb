<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use DateTimeImmutable;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Builder\Expression;
use MongoDB\Builder\Type\Sort;
use MongoDB\Laravel\Tests\Models\User;
use MongoDB\Laravel\Tests\TestCase;

class AggregationsBuilderTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testAggregationBuilderExample(): void
    {
        User::truncate();
        User::insert([
            ['name' => 'Alda Gröndal', 'occupation' => 'engineer', 'birthday' => new UTCDateTime(new DateTimeImmutable('2002-01-01'))],
            ['name' => 'Francois Soma', 'occupation' => 'engineer', 'birthday' => new UTCDateTime(new DateTimeImmutable('1998-02-02'))],
            ['name' => 'Janet Doe', 'occupation' => 'lawyer', 'birthday' => new UTCDateTime(new DateTimeImmutable('1987-03-03'))],
            ['name' => 'Eliud Nkosana', 'occupation' => 'engineer', 'birthday' => new UTCDateTime(new DateTimeImmutable('1984-04-04'))],
            ['name' => 'Bran Steafan', 'occupation' => 'engineer', 'birthday' => new UTCDateTime(new DateTimeImmutable('1998-05-05'))],
        ]);

        // start pipeline example
        $pipeline = User::aggregate()
            ->match(occupation: 'engineer')
            ->addFields(
                year: Expression::year(
                    Expression::dateFieldPath('birthday'),
                ),
            )
            ->sort(year: Sort::Desc, name: Sort::Asc)
            ->unset('birthday', 'occupation');
        // end pipeline example

        $result = $pipeline->get();
        $this->assertEquals(4, $result->count());
    }
}
