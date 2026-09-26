<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Ticket;

use Illuminate\Support\Facades\DB;
use MongoDB\Laravel\Tests\TestCase;

use function array_key_first;
use function array_map;

/**
 * project() must be emitted before $group in the aggregation pipeline so that
 * computed fields are available to sum()/avg()/min()/max()/groupBy().
 *
 * @see https://github.com/mongodb/laravel-mongodb/issues/2339
 */
class GH2339Test extends TestCase
{
    public function tearDown(): void
    {
        DB::table('gh2339_registrations')->truncate();

        parent::tearDown();
    }

    public function testProjectStageComesBeforeGroupInPipeline(): void
    {
        $builder = DB::table('gh2339_registrations')
            ->where('entityType', 'organization')
            ->project([
                'uniqueCount' => [
                    '$size' => [
                        '$cond' => [
                            ['$isArray' => '$visitors'],
                            '$visitors',
                            [],
                        ],
                    ],
                ],
            ]);
        // Simulate sum('uniqueCount') without executing, so toMql() still sees the aggregate.
        $builder->aggregate = ['function' => 'sum', 'columns' => ['uniqueCount']];

        $pipeline = $builder->toMql()['aggregate'][0];
        $stages   = array_map(static fn (array $stage) => array_key_first($stage), $pipeline);

        $this->assertSame(['$match', '$project', '$group'], $stages);
        $this->assertSame(
            [
                '$size' => [
                    '$cond' => [
                        ['$isArray' => '$visitors'],
                        '$visitors',
                        [],
                    ],
                ],
            ],
            $pipeline[1]['$project']['uniqueCount'],
        );
        $this->assertSame(['$sum' => '$uniqueCount'], $pipeline[2]['$group']['aggregate']);
    }

    public function testSumOfProjectedArraySize(): void
    {
        DB::table('gh2339_registrations')->insert([
            [
                'entityType' => 'organization',
                'name' => 'My Cool Company LLC.',
                'visitors' => [
                    ['id' => 'a', 'name' => ['firstName' => 'John', 'lastName' => 'Doe']],
                    ['id' => 'b', 'name' => ['firstName' => 'Jane', 'lastName' => 'Doe']],
                    ['id' => 'c', 'name' => ['firstName' => 'Neil', 'lastName' => 'Young']],
                    ['id' => 'd', 'name' => ['firstName' => 'Ariel', 'lastName' => 'Ortega']],
                ],
            ],
            [
                'entityType' => 'organization',
                'name' => 'Another Org',
                'visitors' => [
                    ['id' => 'e', 'name' => ['firstName' => 'Ada', 'lastName' => 'Lovelace']],
                    ['id' => 'f', 'name' => ['firstName' => 'Grace', 'lastName' => 'Hopper']],
                ],
            ],
            [
                'entityType' => 'person',
                'name' => 'Ignored',
                'visitors' => [
                    ['id' => 'g', 'name' => ['firstName' => 'X', 'lastName' => 'Y']],
                ],
            ],
            [
                'entityType' => 'organization',
                'name' => 'No visitors field',
            ],
        ]);

        $total = DB::table('gh2339_registrations')
            ->where('entityType', 'organization')
            ->project([
                'uniqueCount' => [
                    '$size' => [
                        '$cond' => [
                            ['$isArray' => '$visitors'],
                            '$visitors',
                            [],
                        ],
                    ],
                ],
            ])
            ->sum('uniqueCount');

        // 4 + 2 + 0 = 6
        $this->assertSame(6, (int) $total);
    }

    public function testProjectBeforeGroupBy(): void
    {
        DB::table('gh2339_registrations')->insert([
            ['entityType' => 'organization', 'visitors' => [1, 2, 3], 'region' => 'east'],
            ['entityType' => 'organization', 'visitors' => [1], 'region' => 'east'],
            ['entityType' => 'organization', 'visitors' => [1, 2], 'region' => 'west'],
        ]);

        $builder = DB::table('gh2339_registrations')
            ->project([
                'region' => 1,
                'uniqueCount' => ['$size' => '$visitors'],
            ])
            ->groupBy('region');
        $builder->aggregate = ['function' => 'sum', 'columns' => ['uniqueCount']];

        $stages = array_map(
            static fn (array $stage) => array_key_first($stage),
            $builder->toMql()['aggregate'][0],
        );
        $this->assertSame(['$project', '$group'], $stages);

        $east = DB::table('gh2339_registrations')
            ->where('region', 'east')
            ->project(['uniqueCount' => ['$size' => '$visitors']])
            ->sum('uniqueCount');
        $west = DB::table('gh2339_registrations')
            ->where('region', 'west')
            ->project(['uniqueCount' => ['$size' => '$visitors']])
            ->sum('uniqueCount');

        $this->assertSame(4, (int) $east); // 3 + 1
        $this->assertSame(2, (int) $west);
    }

    public function testPlainSumWithoutProjectStillWorks(): void
    {
        DB::table('gh2339_registrations')->insert([
            ['entityType' => 'organization', 'totalVisits' => 32],
            ['entityType' => 'organization', 'totalVisits' => 10],
        ]);

        $this->assertSame(42, (int) DB::table('gh2339_registrations')->sum('totalVisits'));
    }
}
