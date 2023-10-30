<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Casts;

use Generator;
use MongoDB\BSON\ObjectId;
use MongoDB\Laravel\Tests\Models\Casting;
use MongoDB\Laravel\Tests\TestCase;

class ObjectIdTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Casting::truncate();
    }

    /** @dataProvider provideObjectIdCast */
    public function testStoreObjectId(string|ObjectId $saveObjectId, ObjectId $queryObjectId): void
    {
        $stringObjectId = (string) $saveObjectId;

        Casting::create(['oid' => $saveObjectId]);

        $model = Casting::firstWhere('oid', $queryObjectId);
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

        Casting::create(['oid' => $objectId]);

        $model = Casting::firstWhere('oid', $stringObjectId);
        $this->assertNull($model);
    }
}
