<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Casts;

use Illuminate\Support\Facades\DB;
use MongoDB\Laravel\Tests\Models\BsonCasting;
use MongoDB\Laravel\Tests\TestCase;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;
use stdClass;

use function assert;

/**
 * Mirrors Laravel's Illuminate\Tests\Integration\Database\DatabaseCustomCastsTest
 * for the MongoDB-native AsBsonDocument / AsBsonArray casts.
 *
 * @see https://jira.mongodb.org/browse/PHPLARA-81
 */
class AsBsonDocumentArrayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        BsonCasting::truncate();
    }

    public function testCustomCasting(): void
    {
        $model = new BsonCasting();
        $model->specs = ['name' => 'Taylor'];
        $model->variants = [['name' => 'Taylor']];
        $model->save();

        $model = $model->fresh();

        self::assertInstanceOf(BSONDocument::class, $model->specs);
        self::assertInstanceOf(BSONArray::class, $model->variants);
        self::assertSame(['name' => 'Taylor'], $model->specs->getArrayCopy());
        self::assertSame([['name' => 'Taylor']], $model->variants->getArrayCopy());

        $model->specs['age'] = 34;
        $model->specs['meta']['title'] = 'Developer';
        $model->variants[] = ['name' => 'Otwell'];

        $model->save();
        $model = $model->fresh();

        self::assertSame(
            ['name' => 'Taylor', 'age' => 34, 'meta' => ['title' => 'Developer']],
            $model->specs->getArrayCopy(),
        );
        self::assertSame(
            [['name' => 'Taylor'], ['name' => 'Otwell']],
            $model->variants->getArrayCopy(),
        );
    }

    public function testCustomCastingUsingCreate(): void
    {
        $model = BsonCasting::create([
            'specs' => ['name' => 'Taylor'],
            'variants' => [['name' => 'Taylor']],
        ]);

        $model = $model->fresh();

        self::assertSame(['name' => 'Taylor'], $model->specs->getArrayCopy());
        self::assertSame([['name' => 'Taylor']], $model->variants->getArrayCopy());
    }

    public function testCustomCastingNullableValues(): void
    {
        $model = new BsonCasting();
        $model->specs = null;
        $model->variants = null;
        $model->save();

        $model = $model->fresh();

        self::assertNull($model->specs);
        self::assertNull($model->variants);

        $model->specs = ['name' => 'John'];
        $model->specs['name'] = 'Taylor';
        $model->specs['meta']['title'] = 'Developer';

        $model->variants = [];
        $model->variants[] = ['name' => 'Taylor'];

        $model->save();
        $model = $model->fresh();

        self::assertSame(
            ['name' => 'Taylor', 'meta' => ['title' => 'Developer']],
            $model->specs->getArrayCopy(),
        );
        self::assertSame([['name' => 'Taylor']], $model->variants->getArrayCopy());
    }

    public function testDirtyOnAsBsonDocument(): void
    {
        $model = new BsonCasting();
        $model->setRawAttributes(['specs' => ['bar' => 'foo']]);
        $model->syncOriginal();

        self::assertInstanceOf(BSONDocument::class, $model->specs);
        self::assertFalse($model->isDirty('specs'));

        $model->specs = ['bar' => 'foo'];
        self::assertFalse($model->isDirty('specs'));

        $model->specs = ['baz' => 'foo'];
        self::assertTrue($model->isDirty('specs'));
    }

    public function testDirtyOnAsBsonArray(): void
    {
        $model = new BsonCasting();
        $model->setRawAttributes(['variants' => [['bar' => 'foo']]]);
        $model->syncOriginal();

        self::assertInstanceOf(BSONArray::class, $model->variants);
        self::assertFalse($model->isDirty('variants'));

        $model->variants = [['bar' => 'foo']];
        self::assertFalse($model->isDirty('variants'));

        $model->variants = [['baz' => 'foo']];
        self::assertTrue($model->isDirty('variants'));
    }

    public function testStorageIsNativeBsonNotJsonString(): void
    {
        $model = BsonCasting::create([
            'specs' => ['storage' => ['primary' => '512GB SSD']],
            'variants' => [['color' => 'Blue']],
        ]);

        $raw = DB::connection()->table($model->getTable())->find($model->_id);
        assert($raw instanceof stdClass);

        self::assertIsArray($raw->specs);
        self::assertIsArray($raw->variants);
        self::assertSame('512GB SSD', $raw->specs['storage']['primary']);
        self::assertSame('Blue', $raw->variants[0]['color']);
    }
}
