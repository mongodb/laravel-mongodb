<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Movie;

use BadMethodCallException;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use InvalidArgumentException;
use MongoDB\BSON\Document;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Builder\BuilderEncoder;
use MongoDB\Builder\Expression;
use MongoDB\Builder\Pipeline;
use MongoDB\Builder\Type\Sort;
use MongoDB\Collection as MongoDBCollection;
use MongoDB\Laravel\Query\AggregationBuilder;
use MongoDB\Laravel\Tests\Models\User;
use MongoDB\Laravel\Tests\TestCase;

use MongoDB\Builder\Expression\ResolvesToString;
use MongoDB\Builder\Stage;

class AggregationsBuilderTest extends TestCase
{

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
  public function testAggregationBuilderExample(): void
  {
    User::insert([
            ['name' => 'John Doe', 'birthday' => new UTCDateTime(new DateTimeImmutable('1989-01-01'))],
            ['name' => 'Jane Doe', 'birthday' => new UTCDateTime(new DateTimeImmutable('1990-01-01'))],
        ]);

        // Create the aggregation pipeline from the query builder
        $pipeline = User::aggregate();

        $this->assertInstanceOf(AggregationBuilder::class, $pipeline);

        $pipeline
            ->match(name: 'John Doe')
            ->limit(10)
            ->addFields(
              // Requires MongoDB 5.0+
              // example fullname concat first and last name
                year: Expression::year(
                    Expression::dateFieldPath('birthday'),
                ),
            )
            ->sort(year: Sort::Desc, name: Sort::Asc)
            ->unset('birthday');

        // Compare with the expected pipeline
        $expected = [
            ['$match' => ['name' => 'John Doe']],
            ['$limit' => 10],
            [
                '$addFields' => [
                    'year' => ['$year' => ['date' => '$birthday']],
                ],
            ],
            ['$sort' => ['year' => -1, 'name' => 1]],
            ['$unset' => ['birthday']],
        ];

        $this->assertSamePipeline($expected, $pipeline->getPipeline());
        $pipeline->get();

  }

  public function testAnotherSomething(): void
  {
    $pipeline = new Pipeline(
      Stage::project(
          lowercaseName: lcfirst(
              Expression::stringFieldPath('name'),
          )
      ),
    );


  }

   private static function assertSamePipeline(array $expected, Pipeline $pipeline): void
    {
        $expected = Document::fromPHP(['pipeline' => $expected])->toCanonicalExtendedJSON();

        $codec = new BuilderEncoder();
        $actual = $codec->encode($pipeline);
        // Normalize with BSON round-trip
        $actual = Document::fromPHP(['pipeline' => $actual])->toCanonicalExtendedJSON();

        self::assertJsonStringEqualsJsonString($expected, $actual);
   }


  function lcfirst(ResolvesToString|string $expression): ResolvesToString
{
    return Expression::concat(
        Expression::toLower(
            Expression::substr($expression, 0, 1),
        ),
        Expression::substr(
            $expression,
            Expression::subtract(
                Expression::strLenCP(
                    $expression,
                ),
                1,
            ),
            -1,
        ),
    );
  }

}
