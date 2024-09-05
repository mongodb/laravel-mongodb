<?php

namespace MongoDB\Laravel\Tests\Queue\Failed;

use Illuminate\Queue\Failed\DatabaseFailedJobProvider;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Laravel\Tests\TestCase;
use OutOfBoundsException;

use function array_map;
use function range;
use function sprintf;

/**
 * Ensure the Laravel class {@see DatabaseFailedJobProvider} works with a MongoDB connection.
 */
class DatabaseFailedJobProviderTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        DB::connection('mongodb')
            ->table('failed_jobs')
            ->raw()
            ->insertMany(array_map(static fn ($i) => [
                '_id' => new ObjectId(sprintf('%024d', $i)),
                'connection' => 'mongodb',
                'queue' => $i % 2 ? 'default' : 'other',
                'failed_at' => new UTCDateTime(Date::now()->subHours($i)),
            ], range(1, 5)));
    }

    public function tearDown(): void
    {
        DB::connection('mongodb')
            ->table('failed_jobs')
            ->raw()
            ->drop();

        parent::tearDown();
    }

    public function testLog(): void
    {
        $provider = $this->getProvider();

        $provider->log('mongodb', 'default', '{"foo":"bar"}', new OutOfBoundsException('This is the error'));

        $ids = $provider->ids();

        $this->assertCount(6, $ids);

        $inserted = $provider->find($ids[0]);

        $this->assertSame('mongodb', $inserted->connection);
        $this->assertSame('default', $inserted->queue);
        $this->assertSame('{"foo":"bar"}', $inserted->payload);
        $this->assertStringContainsString('OutOfBoundsException: This is the error', $inserted->exception);
        $this->assertInstanceOf(ObjectId::class, $inserted->id);
    }

    public function testCount(): void
    {
        $provider = $this->getProvider();

        $this->assertEquals(5, $provider->count());
        $this->assertEquals(3, $provider->count('mongodb', 'default'));
        $this->assertEquals(2, $provider->count('mongodb', 'other'));
    }

    public function testAll(): void
    {
        $all = $this->getProvider()->all();

        $this->assertCount(5, $all);
        $this->assertEquals(new ObjectId(sprintf('%024d', 5)), $all[0]->id);
        $this->assertEquals(sprintf('%024d', 5), $all[0]->id, 'id field is added for compatibility with DatabaseFailedJobProvider');
    }

    public function testFindAndForget(): void
    {
        $provider = $this->getProvider();

        $id = sprintf('%024d', 2);
        $found = $provider->find($id);

        $this->assertIsObject($found, 'The job is found');
        $this->assertEquals(new ObjectId($id), $found->id);
        $this->assertObjectHasProperty('failed_at', $found);

        // Delete the job
        $result = $provider->forget($id);

        $this->assertTrue($result, 'forget return true when the job have been deleted');
        $this->assertNull($provider->find($id), 'the job have been deleted');

        // Delete the same job again
        $result = $provider->forget($id);

        $this->assertFalse($result, 'forget return false when the job does not exist');

        $this->assertCount(4, $provider->ids(), 'Other jobs are kept');
    }

    public function testIds(): void
    {
        $ids = $this->getProvider()->ids();

        $this->assertCount(5, $ids);
        $this->assertEquals(new ObjectId(sprintf('%024d', 5)), $ids[0]);
    }

    public function testIdsFilteredByQuery(): void
    {
        $ids = $this->getProvider()->ids('other');

        $this->assertCount(2, $ids);
        $this->assertEquals(new ObjectId(sprintf('%024d', 4)), $ids[0]);
    }

    public function testFlush(): void
    {
        $provider = $this->getProvider();

        $this->assertEquals(5, $provider->count());

        $provider->flush(4);

        $this->assertEquals(3, $provider->count());
    }

    public function testPrune(): void
    {
        $provider = $this->getProvider();

        $this->assertEquals(5, $provider->count());

        $result = $provider->prune(Date::now()->subHours(4));

        $this->assertEquals(2, $result);
        $this->assertEquals(3, $provider->count());
    }

    private function getProvider(): DatabaseFailedJobProvider
    {
        return new DatabaseFailedJobProvider(DB::getFacadeRoot(), '', 'failed_jobs');
    }
}
