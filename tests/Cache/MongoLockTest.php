<?php

namespace MongoDB\Laravel\Tests\Cache;

use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use MongoDB\Laravel\Cache\MongoLock;
use MongoDB\Laravel\Collection;
use MongoDB\Laravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\TestWith;

use function now;

class MongoLockTest extends TestCase
{
    public function tearDown(): void
    {
        DB::connection('mongodb')->getCollection('foo_cache_locks')->drop();

        parent::tearDown();
    }

    #[TestWith([[5, 2]])]
    #[TestWith([['foo', 10]])]
    #[TestWith([[10, 'foo']])]
    #[TestWith([[10]])]
    public function testInvalidLottery(array $lottery)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Lock lottery must be a couple of integers');

        new MongoLock(
            $this->createMock(Collection::class),
            'cache_lock',
            10,
            lottery: $lottery,
        );
    }

    public function testLockCanBeAcquired()
    {
        $lock = $this->getCache()->lock('foo');
        $this->assertTrue($lock->get());
        $this->assertTrue($lock->get());

        $otherLock = $this->getCache()->lock('foo');
        $this->assertFalse($otherLock->get());

        $lock->release();

        $otherLock = $this->getCache()->lock('foo');
        $this->assertTrue($otherLock->get());
        $this->assertTrue($otherLock->get());

        $otherLock->release();
    }

    public function testLockCanBeForceReleased()
    {
        $lock = $this->getCache()->lock('foo');
        $this->assertTrue($lock->get());

        $otherLock = $this->getCache()->lock('foo');
        $otherLock->forceRelease();
        $this->assertTrue($otherLock->get());

        $otherLock->release();
    }

    public function testExpiredLockCanBeRetrieved()
    {
        $lock = $this->getCache()->lock('foo');
        $this->assertTrue($lock->get());
        DB::table('foo_cache_locks')->update(['expires_at' => now()->subDays(1)->getTimestamp()]);

        $otherLock = $this->getCache()->lock('foo');
        $this->assertTrue($otherLock->get());

        $otherLock->release();
    }

    public function testOwnedByCurrentProcess()
    {
        $lock = $this->getCache()->lock('foo');
        $this->assertFalse($lock->isOwnedByCurrentProcess());

        $lock->acquire();
        $this->assertTrue($lock->isOwnedByCurrentProcess());

        $otherLock = $this->getCache()->lock('foo');
        $this->assertFalse($otherLock->isOwnedByCurrentProcess());
    }

    public function testRestoreLock()
    {
        $lock = $this->getCache()->lock('foo');
        $lock->acquire();
        $this->assertInstanceOf(MongoLock::class, $lock);

        $owner = $lock->owner();

        $resoredLock = $this->getCache()->restoreLock('foo', $owner);
        $this->assertTrue($resoredLock->isOwnedByCurrentProcess());

        $resoredLock->release();
        $this->assertFalse($resoredLock->isOwnedByCurrentProcess());
    }

    public function testTTLIndex()
    {
        $store = $this->getCache()->lock('')->createTTLIndex();

        // TTL index remove expired items asynchronously, this test would be very slow
        $indexes = DB::connection('mongodb')->getCollection('foo_cache_locks')->listIndexes();
        $this->assertCount(2, $indexes);
    }

    private function getCache(): Repository
    {
        $repository = Cache::driver('mongodb');

        $this->assertInstanceOf(Repository::class, $repository);

        return $repository;
    }
}
