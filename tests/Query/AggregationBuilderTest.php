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
use MongoDB\Builder\Type\Sort;
use MongoDB\Builder\Type\TimeUnit;
use MongoDB\Builder\Variable;
use MongoDB\Laravel\Query\AggregationBuilder;
use MongoDB\Laravel\Tests\Models\User;
use MongoDB\Laravel\Tests\TestCase;

use function assert;

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
        assert($pipeline instanceof AggregationBuilder);
        $pipeline
            ->addFields(
                age: Expression::dateDiff(
                    startDate: Expression::dateFieldPath('birthday'),
                    endDate: Variable::now(),
                    unit: TimeUnit::Year,
                ),
            )
            ->sort(age: Sort::Desc, name: Sort::Asc)
            ->unset('birthday');

        // The encoder is used to convert the pipeline to a BSON document
        $codec = new BuilderEncoder();
        $json = Document::fromPHP([
            'pipeline' => $codec->encode($pipeline->getPipeline()),
        ])->toCanonicalExtendedJSON();

        // Compare with the expected pipeline
        $expected = Document::fromPHP([
            'pipeline' => [
                ['$match' => ['name' => 'John Doe']],
                ['$limit' => 10],
                [
                    '$addFields' => [
                        'age' => [
                            '$dateDiff' => [
                                'startDate' => '$birthday',
                                'endDate' => '$$NOW',
                                'unit' => 'year',
                            ],
                        ],
                    ],
                ],
                ['$sort' => ['age' => -1, 'name' => 1]],
                ['$unset' => ['birthday']],
            ],
        ])->toCanonicalExtendedJSON();

        $this->assertJsonStringEqualsJsonString($expected, $json);

        // Execute the pipeline and verify the results
        $results = $pipeline->get();

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(1, $results);
        $this->assertInstanceOf(ObjectId::class, $results->first()['_id']);
        $this->assertSame('John Doe', $results->first()['name']);
        $this->assertIsInt($results->first()['age']);
        $this->assertArrayNotHasKey('birthday', $results->first());
    }
}
