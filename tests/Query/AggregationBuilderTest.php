<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Query;

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
use stdClass;

class AggregationBuilderTest extends TestCase
{
    public function tearDown(): void
    {
        User::truncate();

        parent::tearDown();
    }

    public function testCreateAggregationBuilder(): void
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
        $this->assertInstanceOf(ObjectId::class, $results->first()->_id);
        $this->assertSame('John Doe', $results->first()->name);
        $this->assertIsInt($results->first()->year);
        $this->assertObjectNotHasProperty('birthday', $results->first());

        // Execute the pipeline and validate the results in a lazy collection
        $results = $pipeline->cursor();
        $this->assertInstanceOf(LazyCollection::class, $results);

        // Execute the pipeline and return the first result
        $result = $pipeline->first();
        $this->assertInstanceOf(stdClass::class, $result);
        $this->assertInstanceOf(ObjectId::class, $result->_id);
        $this->assertSame('John Doe', $result->name);
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

    public function testAddRawStageInvalid(): void
    {
        $collection = $this->createMock(MongoDBCollection::class);

        $pipeline = new AggregationBuilder($collection);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The stage name "match" is invalid. It must start with a "$" sign.');
        $pipeline->addRawStage('match', ['name' => 'John Doe']);
    }

    public function testColumnsCannotBeSpecifiedToCreateAnAggregationBuilder(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Columns cannot be specified to create an aggregation builder.');
        User::aggregate(null, ['name']);
    }

    public function testAggrecationBuilderDoesNotSupportPreviousQueryBuilderInstructions(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Aggregation builder does not support previous query-builder instructions.');
        User::where('name', 'John Doe')->aggregate();
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
