<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Casts;

use Generator;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;
use MongoDB\Laravel\Tests\Models\CastAsObjectId;
use MongoDB\Laravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class AsObjectIdTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CastAsObjectId::truncate();
    }

    public function testQueryByStringCastsToObjectId(): void
    {
        $objectId       = new ObjectId();
        $stringObjectId = (string) $objectId;

        CastAsObjectId::create(['oid' => $objectId]);

        $model = CastAsObjectId::firstWhere('oid', $stringObjectId);
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

        CastAsObjectId::create(['oid' => $first]);
        CastAsObjectId::create(['oid' => $second]);
        CastAsObjectId::create(['oid' => $third]);

        $models = CastAsObjectId::whereIn('oid', [(string) $first, (string) $second])->get();
        $this->assertCount(2, $models);
        $this->assertEqualsCanonicalizing([(string) $first, (string) $second], $models->pluck('oid')->all());

        $models = CastAsObjectId::whereNotIn('oid', [(string) $first, (string) $second])->get();
        $this->assertCount(1, $models);
        $this->assertSame((string) $third, $models->first()->oid);
    }

    public function testNestedWhereByStringCastsToObjectId(): void
    {
        $objectId = new ObjectId();

        CastAsObjectId::create(['oid' => $objectId, 'name' => 'John Doe']);
        CastAsObjectId::create(['oid' => new ObjectId(), 'name' => 'Jane Doe']);

        // Nested closure
        $model = CastAsObjectId::where(function ($query) use ($objectId) {
            $query->where('oid', (string) $objectId)->orWhere('name', 'Nobody');
        })->first();
        $this->assertNotNull($model);
        $this->assertSame('John Doe', $model->name);

        // Array of wheres, as used by firstOrCreate() and updateOrCreate()
        $model = CastAsObjectId::where(['oid' => (string) $objectId, 'name' => 'John Doe'])->first();
        $this->assertNotNull($model);
        $this->assertSame('John Doe', $model->name);

        $model = CastAsObjectId::firstOrCreate(['oid' => (string) $objectId], ['name' => 'Duplicate']);
        $this->assertSame('John Doe', $model->name);
        $this->assertSame(2, CastAsObjectId::count());
    }

    #[DataProvider('provideNonObjectIdQueryValue')]
    public function testQueryByNonObjectIdValueIsNotConverted(mixed $value): void
    {
        CastAsObjectId::create(['oid' => new ObjectId()]);

        // A value that cannot be an ObjectId is used as-is and does not throw
        $this->assertNull(CastAsObjectId::firstWhere('oid', $value));
        $this->assertSame(1, CastAsObjectId::where('oid', '!=', $value)->count());
    }

    public static function provideNonObjectIdQueryValue(): Generator
    {
        yield 'invalid string' => ['not-an-object-id'];
        yield 'too short hexadecimal string' => ['6708e2cbd36a9f4b8a3c1e2'];
        yield 'too long hexadecimal string' => ['6708e2cbd36a9f4b8a3c1e2f0'];
        yield 'integer' => [12345];
        yield 'null' => [null];
    }

    public function testLikePatternIsNotConverted(): void
    {
        $objectId = new ObjectId();

        CastAsObjectId::create(['oid' => $objectId, 'name' => 'John Doe']);

        // A "like" value must stay a string pattern, converting it would throw a TypeError
        $models = CastAsObjectId::where('oid', 'like', '%%%')->get();
        $this->assertCount(0, $models);
        $this->assertSame(1, CastAsObjectId::where('oid', 'not like', '%%%')->count());
    }
}
