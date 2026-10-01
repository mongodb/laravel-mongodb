<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Encryption;

use MongoDB\BSON\Document;
use MongoDB\Collection;
use MongoDB\Driver\CursorInterface;
use MongoDB\Laravel\Query\AggregationBuilder;
use MongoDB\Laravel\Tests\TestCase;

use function config;

class SafeContentProjectionTest extends TestCase
{
    private const PATIENTS_MAP = [
        'patients' => [
            'fields' => [
                ['path' => 'ssn', 'bsonType' => 'string', 'queries' => [['queryType' => 'equality']]],
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableEncryption(self::PATIENTS_MAP);
    }

    public function testFindOnAnEncryptedCollectionExcludesSafeContent(): void
    {
        $mql = $this->getConnection('mongodb')->table('patients')->toMql();

        $this->assertSame(['__safeContent__' => 0], $mql['find'][1]['projection']);
    }

    public function testFindOnAnUnmappedCollectionIsUntouched(): void
    {
        $mql = $this->getConnection('mongodb')->table('items')->toMql();

        $this->assertArrayNotHasKey('projection', $mql['find'][1]);
    }

    public function testFindWithoutEncryptionConfiguredIsUntouched(): void
    {
        config(['database.connections.mongodb.driver_options.autoEncryption' => null]);
        $this->app->make('db')->purge('mongodb');

        $mql = $this->getConnection('mongodb')->table('patients')->toMql();

        $this->assertArrayNotHasKey('projection', $mql['find'][1]);
    }

    public function testSelectedColumnsAlreadyDropSafeContent(): void
    {
        $mql = $this->getConnection('mongodb')->table('patients')->select('ssn')->toMql();

        $this->assertSame(['ssn' => true], $mql['find'][1]['projection']);
    }

    public function testExcludedColumnsKeepTheExclusion(): void
    {
        $mql = $this->getConnection('mongodb')
            ->table('patients')
            ->project(['ssn' => 0])
            ->toMql();

        $this->assertSame(['ssn' => 0, '__safeContent__' => 0], $mql['find'][1]['projection']);
    }

    public function testGroupedQueryIsUntouched(): void
    {
        $mql = $this->getConnection('mongodb')
            ->table('patients')
            ->groupBy('ssn')
            ->toMql();

        $this->assertSame([['$group' => ['_id' => ['ssn' => '$ssn'], 'ssn' => ['$last' => '$ssn']]]], $mql['aggregate'][0]);
    }

    public function testAggregationOnAnEncryptedCollectionUnsetsSafeContent(): void
    {
        $sent = $this->pipelineSentBy(
            fn (AggregationBuilder $pipeline) => $pipeline->match(ssn: '123')->get(),
            true,
        );

        $this->assertSamePipeline(
            [['$match' => ['ssn' => '123']], ['$unset' => '__safeContent__']],
            $sent,
        );
    }

    public function testAggregationOnAnUnmappedCollectionIsUntouched(): void
    {
        $sent = $this->pipelineSentBy(
            fn (AggregationBuilder $pipeline) => $pipeline->match(ssn: '123')->get(),
            false,
        );

        $this->assertSamePipeline([['$match' => ['ssn' => '123']]], $sent);
    }

    public function testAggregationWritingOutKeepsTheTerminalStageLast(): void
    {
        $sent = $this->pipelineSentBy(
            fn (AggregationBuilder $pipeline) => $pipeline->match(ssn: '123')->out('copies')->get(),
            true,
        );

        $this->assertSamePipeline(
            [['$match' => ['ssn' => '123']], ['$out' => ['coll' => 'copies']]],
            $sent,
        );
    }

    /**
     * The pipeline the builder hands to the collection, captured instead of
     * run.
     *
     * @return list<array<string, mixed>|object>
     */
    private function pipelineSentBy(callable $execute, bool $hidesSafeContent): array
    {
        $sent = [];
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->once())
            ->method('aggregate')
            ->willReturnCallback(function (array $pipeline) use (&$sent): CursorInterface {
                $sent = $pipeline;

                return $this->createStub(CursorInterface::class);
            });

        $execute(new AggregationBuilder($collection, [], $hidesSafeContent));

        return $sent;
    }

    /**
     * @param list<array<string, mixed>>        $expected
     * @param list<array<string, mixed>|object> $actual
     */
    private function assertSamePipeline(array $expected, array $actual): void
    {
        $this->assertSame(
            Document::fromPHP(['pipeline' => $expected])->toCanonicalExtendedJSON(),
            Document::fromPHP(['pipeline' => $actual])->toCanonicalExtendedJSON(),
        );
    }
}
