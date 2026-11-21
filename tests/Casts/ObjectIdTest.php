<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Casts;

use Generator;
use MongoDB\BSON\ObjectId;
use MongoDB\Laravel\Tests\Models\CastObjectId;
use MongoDB\Laravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class ObjectIdTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CastObjectId::truncate();
    }

    #[DataProvider('provideObjectIdCast')]
    public function testStoreObjectId(string|ObjectId $saveObjectId, ObjectId $queryObjectId): void
    {
        $stringObjectId = (string) $saveObjectId;

        CastObjectId::create(['oid' => $saveObjectId]);

        $model = CastObjectId::firstWhere('oid', $queryObjectId);
        $this->assertNotNull($model);
        $this->assertSame($stringObjectId, $model->oid);
    }

    public static function provideObjectIdCast(): Generator
    {
        $objectId       = new ObjectId();
        $stringObjectId = (string) $objectId;

        yield 'Save ObjectId, Query ObjectId' => [$objectId, $objectId];
        yield 'Save string, Query ObjectId' => [$stringObjectId, $objectId];
    }

    public function testQueryByStringDoesNotCast(): void
    {
        $objectId       = new ObjectId();
        $stringObjectId = (string) $objectId;

        CastObjectId::create(['oid' => $objectId]);

        $model = CastObjectId::firstWhere('oid', $stringObjectId);
        $this->assertNull($model);
    }

    public function testStoreNull(): void
    {
        CastObjectId::create(['oid' => null]);

        $model = CastObjectId::first();
        $this->assertNull($model->oid);
        $this->assertNull($model->getRawOriginal('oid'));
    }

    public function testClearToNull(): void
    {
        $model = CastObjectId::create(['oid' => new ObjectId()]);

        $model->oid = null;
        $model->save();

        $this->assertNull($model->fresh()->getRawOriginal('oid'));
    }
}
