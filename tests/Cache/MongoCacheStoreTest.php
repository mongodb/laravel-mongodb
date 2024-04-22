<?php

namespace MongoDB\Laravel\Tests\Cache;

use Illuminate\Cache\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use MongoDB\Laravel\Tests\TestCase;

use function assert;
use function config;

/** Tests imported from {@see \Illuminate\Tests\Integration\Database\DatabaseCacheStoreTest} */
class MongoCacheStoreTest extends TestCase
{
    public function tearDown(): void
    {
        DB::connection('mongodb')
            ->getCollection($this->getCacheCollectionName())
            ->drop();

        parent::tearDown();
    }

    public function testGetNullWhenItemDoesNotExist()
    {
        $store = $this->getStore();
        $this->assertNull($store->get('foo'));
    }

    public function testValueCanStoreNewCache()
    {
        $store = $this->getStore();

        $store->put('foo', 'bar', 60);

        $this->assertSame('bar', $store->get('foo'));
    }

    public function testPutOperationShouldNotStoreExpired()
    {
        $store = $this->getStore();

        $store->put('foo', 'bar', 0);

        $this->assertDatabaseMissing($this->getCacheCollectionName(), ['_id' => $this->withCachePrefix('foo')]);
    }

    public function testValueCanUpdateExistCache()
    {
        $store = $this->getStore();

        $store->put('foo', 'bar', 60);
        $store->put('foo', 'new-bar', 60);

        $this->assertSame('new-bar', $store->get('foo'));
    }

    public function testValueCanUpdateExistCacheInTransaction()
    {
        $store = $this->getStore();

        $store->put('foo', 'bar', 60);

        // Transactions are not used in MongoStore
        DB::beginTransaction();
        $store->put('foo', 'new-bar', 60);
        DB::commit();

        $this->assertSame('new-bar', $store->get('foo'));
    }

    public function testAddOperationShouldNotStoreExpired()
    {
        $store = $this->getStore();

        $result = $store->add('foo', 'bar', 0);

        $this->assertFalse($result);
        $this->assertDatabaseMissing($this->getCacheCollectionName(), ['_id' => $this->withCachePrefix('foo')]);
    }

    public function testAddOperationCanStoreNewCache()
    {
        $store = $this->getStore();

        $result = $store->add('foo', 'bar', 60);

        $this->assertTrue($result);
        $this->assertSame('bar', $store->get('foo'));
    }

    public function testAddOperationShouldNotUpdateExistCache()
    {
        $store = $this->getStore();

        $store->add('foo', 'bar', 60);
        $result = $store->add('foo', 'new-bar', 60);

        $this->assertFalse($result);
        $this->assertSame('bar', $store->get('foo'));
    }

    public function testAddOperationShouldNotUpdateExistCacheInTransaction()
    {
        $store = $this->getStore();

        $store->add('foo', 'bar', 60);

        DB::beginTransaction();
        $result = $store->add('foo', 'new-bar', 60);
        DB::commit();

        $this->assertFalse($result);
        $this->assertSame('bar', $store->get('foo'));
    }

    public function testAddOperationCanUpdateIfCacheExpired()
    {
        $store = $this->getStore();

        $this->insertToCacheTable('foo', 'bar', 0);
        $result = $store->add('foo', 'new-bar', 60);

        $this->assertTrue($result);
        $this->assertSame('new-bar', $store->get('foo'));
    }

    public function testAddOperationCanUpdateIfCacheExpiredInTransaction()
    {
        $store = $this->getStore();

        $this->insertToCacheTable('foo', 'bar', 0);

        DB::beginTransaction();
        $result = $store->add('foo', 'new-bar', 60);
        DB::commit();

        $this->assertTrue($result);
        $this->assertSame('new-bar', $store->get('foo'));
    }

    public function testGetOperationReturnNullIfExpired()
    {
        $store = $this->getStore();

        $this->insertToCacheTable('foo', 'bar', 0);

        $result = $store->get('foo');

        $this->assertNull($result);
    }

    public function testGetOperationCanDeleteExpired()
    {
        $store = $this->getStore();

        $this->insertToCacheTable('foo', 'bar', 0);

        $store->get('foo');

        $this->assertDatabaseMissing($this->getCacheCollectionName(), ['_id' => $this->withCachePrefix('foo')]);
    }

    public function testForgetIfExpiredOperationCanDeleteExpired()
    {
        $store = $this->getStore();

        $this->insertToCacheTable('foo', 'bar', 0);

        $store->forgetIfExpired('foo');

        $this->assertDatabaseMissing($this->getCacheCollectionName(), ['_id' => $this->withCachePrefix('foo')]);
    }

    public function testForgetIfExpiredOperationShouldNotDeleteUnExpired()
    {
        $store = $this->getStore();

        $store->put('foo', 'bar', 60);

        $store->forgetIfExpired('foo');

        $this->assertDatabaseHas($this->getCacheCollectionName(), ['_id' => $this->withCachePrefix('foo')]);
    }

    public function testIncrementDecrement()
    {
        $store = $this->getStore();
        $this->assertFalse($store->increment('foo', 10));
        $this->assertFalse($store->decrement('foo', 10));

        $store->put('foo', 3.5, 60);
        $this->assertSame(13.5, $store->increment('foo', 10));
        $this->assertSame(12.0, $store->decrement('foo', 1.5));
        $store->forget('foo');

        $this->insertToCacheTable('foo', 10, -5);
        $this->assertFalse($store->increment('foo', 5));
    }

    protected function getStore(): Repository
    {
        $repository = Cache::store('mongodb');
        assert($repository instanceof Repository);

        return $repository;
    }

    protected function getCacheCollectionName(): string
    {
        return config('cache.stores.mongodb.collection');
    }

    protected function withCachePrefix(string $key): string
    {
        return config('cache.prefix') . $key;
    }

    protected function insertToCacheTable(string $key, $value, $ttl = 60)
    {
        DB::connection('mongodb')
            ->getCollection($this->getCacheCollectionName())
            ->insertOne([
                '_id' => $this->withCachePrefix($key),
                'value' => $value,
                'expiration' => Carbon::now()->addSeconds($ttl)->getTimestamp(),
            ]);
    }
}
