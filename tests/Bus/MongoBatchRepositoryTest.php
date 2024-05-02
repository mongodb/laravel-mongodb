<?php

namespace MongoDB\Laravel\Tests\Bus;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\BatchFactory;
use Illuminate\Bus\PendingBatch;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Factory;
use Illuminate\Queue\CallQueuedClosure;
use Illuminate\Support\Facades\DB;
use Illuminate\Tests\Bus\BusBatchTest;
use Mockery as m;
use MongoDB\Laravel\Bus\MongoBatchRepository;
use MongoDB\Laravel\Connection;
use MongoDB\Laravel\Tests\Bus\Fixtures\ChainHeadJob;
use MongoDB\Laravel\Tests\Bus\Fixtures\SecondTestJob;
use MongoDB\Laravel\Tests\Bus\Fixtures\ThirdTestJob;
use MongoDB\Laravel\Tests\TestCase;
use RuntimeException;
use stdClass;

use function collect;
use function is_string;
use function json_encode;
use function now;
use function serialize;

final class MongoBatchRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['__finally.count'] = 0;
        $_SERVER['__progress.count'] = 0;
        $_SERVER['__then.count'] = 0;
        $_SERVER['__catch.count'] = 0;
    }

    protected function tearDown(): void
    {
        DB::connection('mongodb')->getCollection('job_batches')->drop();

        unset(
            $_SERVER['__catch.batch'],
            $_SERVER['__catch.count'],
            $_SERVER['__catch.exception'],
            $_SERVER['__finally.batch'],
            $_SERVER['__finally.count'],
            $_SERVER['__progress.batch'],
            $_SERVER['__progress.count'],
            $_SERVER['__then.batch'],
            $_SERVER['__then.count'],
        );

        parent::tearDown();
    }

    /** @see BusBatchTest::test_jobs_can_be_added_to_the_batch */
    public function testJobsCanBeAddedToTheBatch(): void
    {
        $queue = m::mock(Factory::class);

        $batch = $this->createTestBatch($queue);

        $job = new class
        {
            use Batchable;
        };

        $secondJob = new class
        {
            use Batchable;
        };

        $thirdJob = function () {
        };

        $queue->shouldReceive('connection')->once()
            ->with('test-connection')
            ->andReturn($connection = m::mock(stdClass::class));

        $connection->shouldReceive('bulk')->once()->with(m::on(function ($args) use ($job, $secondJob) {
            return $args[0] === $job &&
                $args[1] === $secondJob &&
                $args[2] instanceof CallQueuedClosure
                && is_string($args[2]->batchId);
        }), '', 'test-queue');

        $batch = $batch->add([$job, $secondJob, $thirdJob]);

        $this->assertEquals(3, $batch->totalJobs);
        $this->assertEquals(3, $batch->pendingJobs);
        $this->assertIsString($job->batchId);
        $this->assertInstanceOf(CarbonImmutable::class, $batch->createdAt);
    }

    /** @see BusBatchTest::test_successful_jobs_can_be_recorded */
    public function testSuccessfulJobsCanBeRecorded()
    {
        $queue = m::mock(Factory::class);

        $batch = $this->createTestBatch($queue);

        $job = new class
        {
            use Batchable;
        };

        $secondJob = new class
        {
            use Batchable;
        };

        $queue->shouldReceive('connection')->once()
            ->with('test-connection')
            ->andReturn($connection = m::mock(stdClass::class));

        $connection->shouldReceive('bulk')->once();

        $batch = $batch->add([$job, $secondJob]);
        $this->assertEquals(2, $batch->pendingJobs);

        $batch->recordSuccessfulJob('test-id');
        $batch->recordSuccessfulJob('test-id');

        $this->assertInstanceOf(Batch::class, $_SERVER['__finally.batch']);
        $this->assertInstanceOf(Batch::class, $_SERVER['__progress.batch']);
        $this->assertInstanceOf(Batch::class, $_SERVER['__then.batch']);

        $batch = $batch->fresh();
        $this->assertEquals(0, $batch->pendingJobs);
        $this->assertTrue($batch->finished());
        $this->assertEquals(1, $_SERVER['__finally.count']);
        $this->assertEquals(2, $_SERVER['__progress.count']);
        $this->assertEquals(1, $_SERVER['__then.count']);
    }

    /** @see BusBatchTest::test_failed_jobs_can_be_recorded_while_not_allowing_failures */
    public function testFailedJobsCanBeRecordedWhileNotAllowingFailures()
    {
        $queue = m::mock(Factory::class);

        $batch = $this->createTestBatch($queue, $allowFailures = false);

        $job = new class
        {
            use Batchable;
        };

        $secondJob = new class
        {
            use Batchable;
        };

        $queue->shouldReceive('connection')->once()
            ->with('test-connection')
            ->andReturn($connection = m::mock(stdClass::class));

        $connection->shouldReceive('bulk')->once();

        $batch = $batch->add([$job, $secondJob]);
        $this->assertEquals(2, $batch->pendingJobs);

        $batch->recordFailedJob('test-id', new RuntimeException('Something went wrong.'));
        $batch->recordFailedJob('test-id', new RuntimeException('Something else went wrong.'));

        $this->assertInstanceOf(Batch::class, $_SERVER['__finally.batch']);
        $this->assertFalse(isset($_SERVER['__then.batch']));

        $batch = $batch->fresh();
        $this->assertEquals(2, $batch->pendingJobs);
        $this->assertEquals(2, $batch->failedJobs);
        $this->assertTrue($batch->finished());
        $this->assertTrue($batch->cancelled());
        $this->assertEquals(1, $_SERVER['__finally.count']);
        $this->assertEquals(0, $_SERVER['__progress.count']);
        $this->assertEquals(1, $_SERVER['__catch.count']);
        $this->assertSame('Something went wrong.', $_SERVER['__catch.exception']->getMessage());
    }

    /** @see BusBatchTest::test_failed_jobs_can_be_recorded_while_allowing_failures */
    public function testFailedJobsCanBeRecordedWhileAllowingFailures()
    {
        $queue = m::mock(Factory::class);

        $batch = $this->createTestBatch($queue, $allowFailures = true);

        $job = new class
        {
            use Batchable;
        };

        $secondJob = new class
        {
            use Batchable;
        };

        $queue->shouldReceive('connection')->once()
            ->with('test-connection')
            ->andReturn($connection = m::mock(stdClass::class));

        $connection->shouldReceive('bulk')->once();

        $batch = $batch->add([$job, $secondJob]);
        $this->assertEquals(2, $batch->pendingJobs);

        $batch->recordFailedJob('test-id', new RuntimeException('Something went wrong.'));
        $batch->recordFailedJob('test-id', new RuntimeException('Something else went wrong.'));

        // While allowing failures this batch never actually completes...
        $this->assertFalse(isset($_SERVER['__then.batch']));

        $batch = $batch->fresh();
        $this->assertEquals(2, $batch->pendingJobs);
        $this->assertEquals(2, $batch->failedJobs);
        $this->assertFalse($batch->finished());
        $this->assertFalse($batch->cancelled());
        $this->assertEquals(1, $_SERVER['__catch.count']);
        $this->assertEquals(2, $_SERVER['__progress.count']);
        $this->assertSame('Something went wrong.', $_SERVER['__catch.exception']->getMessage());
    }

    /** @see BusBatchTest::test_batch_can_be_cancelled */
    public function testBatchCanBeCancelled()
    {
        $queue = m::mock(Factory::class);

        $batch = $this->createTestBatch($queue);

        $batch->cancel();

        $batch = $batch->fresh();

        $this->assertTrue($batch->cancelled());
    }

    /** @see BusBatchTest::test_batch_can_be_deleted */
    public function testBatchCanBeDeleted()
    {
        $queue = m::mock(Factory::class);

        $batch = $this->createTestBatch($queue);

        $batch->delete();

        $batch = $batch->fresh();

        $this->assertNull($batch);
    }

    /** @see BusBatchTest::test_batch_state_can_be_inspected */
    public function testBatchStateCanBeInspected()
    {
        $queue = m::mock(Factory::class);

        $batch = $this->createTestBatch($queue);

        $this->assertFalse($batch->finished());
        $batch->finishedAt = now();
        $this->assertTrue($batch->finished());

        $batch->options['progress'] = [];
        $this->assertFalse($batch->hasProgressCallbacks());
        $batch->options['progress'] = [1];
        $this->assertTrue($batch->hasProgressCallbacks());

        $batch->options['then'] = [];
        $this->assertFalse($batch->hasThenCallbacks());
        $batch->options['then'] = [1];
        $this->assertTrue($batch->hasThenCallbacks());

        $this->assertFalse($batch->allowsFailures());
        $batch->options['allowFailures'] = true;
        $this->assertTrue($batch->allowsFailures());

        $this->assertFalse($batch->hasFailures());
        $batch->failedJobs = 1;
        $this->assertTrue($batch->hasFailures());

        $batch->options['catch'] = [];
        $this->assertFalse($batch->hasCatchCallbacks());
        $batch->options['catch'] = [1];
        $this->assertTrue($batch->hasCatchCallbacks());

        $this->assertFalse($batch->cancelled());
        $batch->cancelledAt = now();
        $this->assertTrue($batch->cancelled());

        $this->assertIsString(json_encode($batch));
    }

    /** @see BusBatchTest:test_chain_can_be_added_to_batch: */
    public function testChainCanBeAddedToBatch()
    {
        $queue = m::mock(Factory::class);

        $batch = $this->createTestBatch($queue);

        $chainHeadJob = new ChainHeadJob();

        $secondJob = new SecondTestJob();

        $thirdJob = new ThirdTestJob();

        $queue->shouldReceive('connection')->once()
            ->with('test-connection')
            ->andReturn($connection = m::mock(stdClass::class));

        $connection->shouldReceive('bulk')->once()->with(m::on(function ($args) use ($chainHeadJob, $secondJob, $thirdJob) {
            return $args[0] === $chainHeadJob
                && serialize($secondJob) === $args[0]->chained[0]
                && serialize($thirdJob) === $args[0]->chained[1];
        }), '', 'test-queue');

        $batch = $batch->add([
            [$chainHeadJob, $secondJob, $thirdJob],
        ]);

        $this->assertEquals(3, $batch->totalJobs);
        $this->assertEquals(3, $batch->pendingJobs);
        $this->assertSame('test-queue', $chainHeadJob->chainQueue);
        $this->assertIsString($chainHeadJob->batchId);
        $this->assertIsString($secondJob->batchId);
        $this->assertIsString($thirdJob->batchId);
        $this->assertInstanceOf(CarbonImmutable::class, $batch->createdAt);
    }

    /** @see BusBatchTest::createTestBatch() */
    private function createTestBatch(Factory $queue, $allowFailures = false)
    {
        $connection = DB::connection('mongodb');
        $this->assertInstanceOf(Connection::class, $connection);

        $repository = new MongoBatchRepository(new BatchFactory($queue), $connection, 'job_batches');

        $pendingBatch = (new PendingBatch(new Container(), collect()))
            ->progress(function (Batch $batch) {
                $_SERVER['__progress.batch'] = $batch;
                $_SERVER['__progress.count']++;
            })
            ->then(function (Batch $batch) {
                $_SERVER['__then.batch'] = $batch;
                $_SERVER['__then.count']++;
            })
            ->catch(function (Batch $batch, $e) {
                $_SERVER['__catch.batch'] = $batch;
                $_SERVER['__catch.exception'] = $e;
                $_SERVER['__catch.count']++;
            })
            ->finally(function (Batch $batch) {
                $_SERVER['__finally.batch'] = $batch;
                $_SERVER['__finally.count']++;
            })
            ->allowFailures($allowFailures)
            ->onConnection('test-connection')
            ->onQueue('test-queue');

        return $repository->store($pendingBatch);
    }
}
