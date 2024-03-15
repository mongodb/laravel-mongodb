<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Query;

use DateTimeImmutable;
use Illuminate\Support\Collection;
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

class AggregationBuilderTest extends TestCase
{
    public function tearDown(): void
    {
        User::truncate();
    }

    public function testCreateFromQueryBuilder(): void
    {
        User::insert([
            ['name' => 'John Doe', 'birthday' => new UTCDateTime(new DateTimeImmutable('1989-01-01'))],
            ['name' => 'Jane Doe', 'birthday' => new UTCDateTime(new DateTimeImmutable('1990-01-01'))],
        ]);

        // Create the aggregation pipeline from the query builder
        $pipeline = User::where('name', 'John Doe')
            ->limit(10)
            ->offset(0)
            ->aggregate();

        $this->assertInstanceOf(AggregationBuilder::class, $pipeline);

        $pipeline
            ->addFields(
                // Requires MongoDB 5.0+
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

        // Execute the pipeline and validate the results
        $results = $pipeline->get();

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(1, $results);
        $this->assertInstanceOf(ObjectId::class, $results->first()['_id']);
        $this->assertSame('John Doe', $results->first()['name']);
        $this->assertIsInt($results->first()['year']);
        $this->assertArrayNotHasKey('birthday', $results->first());
    }

    public function testAddRawStage(): void
    {
        $collection = $this->createMock(MongoDBCollection::class);

        $pipeline = new AggregationBuilder($collection);
        $pipeline
            ->addRawStage('$match', ['name' => 'John Doe'])
            ->addRawStage('$limit', 10)
            ->addRawStage('$replaceRoot', (object) ['newRoot' => '$$ROOT']);

        $expected = [
            ['$match' => ['name' => 'John Doe']],
            ['$limit' => 10],
            ['$replaceRoot' => ['newRoot' => '$$ROOT']],
        ];

        $this->assertSamePipeline($expected, $pipeline->getPipeline());
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
}
