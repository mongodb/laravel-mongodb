<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Casts;

use Generator;
use Illuminate\Support\Facades\DB;
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

    public function testQueryByStringCastsToObjectId(): void
    {
        $objectId       = new ObjectId();
        $stringObjectId = (string) $objectId;

        CastObjectId::create(['oid' => $objectId]);

        $model = CastObjectId::firstWhere('oid', $stringObjectId);
        $this->assertNotNull($model);
        $this->assertSame($stringObjectId, $model->oid);

        // The stored value keeps its native BSON type.
        $document = DB::connection('mongodb')->getCollection($model->getTable())->findOne();
        $this->assertInstanceOf(ObjectId::class, $document['oid']);
        $this->assertEquals($objectId, $document['oid']);
    }

    public function testWhereInByStringCastsToObjectId(): void
    {
        $first  = new ObjectId();
        $second = new ObjectId();
        $third  = new ObjectId();

        CastObjectId::create(['oid' => $first]);
        CastObjectId::create(['oid' => $second]);
        CastObjectId::create(['oid' => $third]);

        $models = CastObjectId::whereIn('oid', [(string) $first, (string) $second])->get();
        $this->assertCount(2, $models);
        $this->assertEqualsCanonicalizing([(string) $first, (string) $second], $models->pluck('oid')->all());

        $models = CastObjectId::whereNotIn('oid', [(string) $first, (string) $second])->get();
        $this->assertCount(1, $models);
        $this->assertSame((string) $third, $models->first()->oid);
    }

    public function testNestedWhereByStringCastsToObjectId(): void
    {
        $objectId = new ObjectId();

        CastObjectId::create(['oid' => $objectId, 'name' => 'John Doe']);
        CastObjectId::create(['oid' => new ObjectId(), 'name' => 'Jane Doe']);

        // Nested closure
        $model = CastObjectId::where(function ($query) use ($objectId) {
            $query->where('oid', (string) $objectId)->orWhere('name', 'Nobody');
        })->first();
        $this->assertNotNull($model);
        $this->assertSame('John Doe', $model->name);

        // Array of wheres, as used by firstOrCreate() and updateOrCreate()
        $model = CastObjectId::where(['oid' => (string) $objectId, 'name' => 'John Doe'])->first();
        $this->assertNotNull($model);
        $this->assertSame('John Doe', $model->name);

        $model = CastObjectId::firstOrCreate(['oid' => (string) $objectId], ['name' => 'Duplicate']);
        $this->assertSame('John Doe', $model->name);
        $this->assertSame(2, CastObjectId::count());
    }

    #[DataProvider('provideNonObjectIdQueryValue')]
    public function testQueryByNonObjectIdValueIsNotConverted(mixed $value): void
    {
        CastObjectId::create(['oid' => new ObjectId()]);

        // A value that cannot be an ObjectId is used as-is and does not throw
        $this->assertNull(CastObjectId::firstWhere('oid', $value));
        $this->assertSame(1, CastObjectId::where('oid', '!=', $value)->count());
    }

    public static function provideNonObjectIdQueryValue(): Generator
    {
        yield 'invalid string' => ['not-an-object-id'];
        yield 'too short hexadecimal string' => ['6708e2cbd36a9f4b8a3c1e2'];
        yield 'too long hexadecimal string' => ['6708e2cbd36a9f4b8a3c1e2f0'];
        yield 'integer' => [12345];
        yield 'null' => [null];
    }
}
