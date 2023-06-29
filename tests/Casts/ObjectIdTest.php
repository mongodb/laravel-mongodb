<?php

namespace Jenssegers\Mongodb\Tests\Casts;

use Generator;
use Jenssegers\Mongodb\Tests\Models\CastObjectId;
use Jenssegers\Mongodb\Tests\TestCase;
use MongoDB\BSON\ObjectId;

class ObjectIdTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CastObjectId::truncate();
    }

    /** @dataProvider provideObjectIdCast */
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
        $objectId = new ObjectId();
        $stringObjectId = (string) $objectId;

        yield 'Save ObjectId, Query ObjectId' => [$objectId, $objectId];
        yield 'Save string, Query ObjectId' => [$stringObjectId, $objectId];
    }

    public function testQueryByStringDoesNotCast(): void
    {
        $objectId = new ObjectId();
        $stringObjectId = (string) $objectId;

        CastObjectId::create(['oid' => $objectId]);

        $model = CastObjectId::firstWhere('oid', $stringObjectId);
        $this->assertNull($model);
    }
}
