<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Queue\Failed;

use Illuminate\Queue\Failed\DatabaseFailedJobProvider;
use Illuminate\Queue\Failed\DatabaseUuidFailedJobProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use MongoDB\Laravel\Queue\Failed\MongoFailedJobProvider;
use MongoDB\Laravel\Tests\TestCase;
use RuntimeException;

use function app;
use function config;
use function json_encode;
use function sprintf;

class MongoFailedJobProviderTest extends TestCase
{
    public function tearDown(): void
    {
        DB::connection('mongodb')->table('failed_jobs')->raw()->drop();
        DB::connection('mongodb')->table('jobs')->raw()->drop();

        parent::tearDown();
    }

    public function testIdsAreStringsThatCanBeArrayKeys(): void
    {
        $provider = $this->failer();
        $this->logFailedJob($provider, 'default');
        $this->logFailedJob($provider, 'other');

        $ids = $provider->ids();

        $this->assertCount(2, $ids);
        foreach ($ids as $id) {
            $this->assertIsString($id);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{24}$/', $id);

            $found = $provider->find($id);
            $this->assertNotNull($found);

            $retried = new Collection([$id => $found]);
            $this->assertTrue($retried->has($id));
        }

        $otherIds = $provider->ids('other');
        $this->assertCount(1, $otherIds);
        $this->assertIsString($otherIds[0]);
        $this->assertSame('other', $provider->find($otherIds[0])->queue);

        $this->assertTrue($provider->forget($otherIds[0]));
        $this->assertNull($provider->find($otherIds[0]));
        $this->assertCount(1, $provider->ids());
    }

    public function testQueueRetryAllPushesTheJobAndForgetsIt(): void
    {
        $provider = $this->failer();
        $this->logFailedJob($provider, 'default');
        $this->logFailedJob($provider, 'other');

        $this->artisan('queue:retry', ['--queue' => 'other'])->assertSuccessful();

        $remaining = $provider->ids();
        $this->assertCount(1, $remaining);
        $this->assertSame('default', $provider->find($remaining[0])->queue);
        $this->assertSame(1, DB::connection('mongodb')->table('jobs')->count());

        $this->artisan('queue:retry', ['id' => ['all']])->assertSuccessful();

        $this->assertSame([], $provider->ids());
        $this->assertSame(2, DB::connection('mongodb')->table('jobs')->count());
    }

    public function testDatabaseDriverOnMongoConnectionIsReplaced(): void
    {
        config([
            'queue.failed.driver' => 'database',
            'queue.failed.database' => 'mongodb2',
        ]);
        $this->app->forgetInstance('queue.failer');

        $this->assertInstanceOf(MongoFailedJobProvider::class, app('queue.failer'));
    }

    public function testMissingDatabaseUsesTheDefaultMongoConnection(): void
    {
        config(['queue.failed.database' => null]);
        $this->app->forgetInstance('queue.failer');

        $provider = app('queue.failer');

        $this->assertInstanceOf(MongoFailedJobProvider::class, $provider);
        $this->logFailedJob($provider, 'default');
        $this->assertCount(1, $provider->ids());
    }

    public function testNonMongoConnectionKeepsLaravelProvider(): void
    {
        config([
            'queue.failed.driver' => 'database',
            'queue.failed.database' => 'sqlite',
        ]);
        $this->app->forgetInstance('queue.failer');

        $provider = app('queue.failer');

        $this->assertInstanceOf(DatabaseFailedJobProvider::class, $provider);
        $this->assertNotInstanceOf(MongoFailedJobProvider::class, $provider);
    }

    public function testDatabaseUuidsProviderIsLeftAlone(): void
    {
        config([
            'queue.failed.driver' => 'database-uuids',
            'queue.failed.database' => 'mongodb2',
            'queue.failed.table' => 'failed_jobs',
        ]);
        $this->app->forgetInstance('queue.failer');

        $this->assertInstanceOf(DatabaseUuidFailedJobProvider::class, app('queue.failer'));
    }

    private function failer(): MongoFailedJobProvider
    {
        $provider = app('queue.failer');

        if (! $provider instanceof MongoFailedJobProvider) {
            $this->fail('Expected the MongoDB failed job provider to be registered.');
        }

        return $provider;
    }

    private function logFailedJob(MongoFailedJobProvider $provider, string $queue): void
    {
        $provider->log(
            'database',
            $queue,
            json_encode([
                'uuid' => sprintf('00000000-0000-4000-8000-%012d', $queue === 'default' ? 1 : 2),
                'displayName' => 'X',
                'job' => 'X',
                'data' => [],
            ]),
            new RuntimeException('boom'),
        );
    }
}
