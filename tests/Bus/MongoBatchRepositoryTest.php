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
use Illuminate\Queue\Queue;
use Illuminate\Support\Facades\DB;
use Illuminate\Tests\Bus\BusBatchTest;
use Mockery as m;
use MongoDB\Laravel\Bus\MongoBatchRepository;
use MongoDB\Laravel\Connection;
use MongoDB\Laravel\Tests\TestCase;
use stdClass;

use function collect;
use function is_string;

final class MongoBatchRepositoryTest extends TestCase
{
    private Queue $queue;

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

    /** @see BusBatchTest::createTestBatch() */
    private function createTestBatch($queue, $allowFailures = false)
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
