<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Casts;

use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;
use MongoDB\Laravel\Tests\Models\Casting;
use MongoDB\Laravel\Tests\TestCase;

class ArrayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Casting::truncate();
    }

    public function testArray(): void
    {
        /** @var Casting $model */
        $model = Casting::query()->create(['arrayValue' => ["Dreamin' 'bout the spot that right now, I'm actually in", 'g-eazy' => 'Still']]);

        self::assertIsArray($model->arrayValue);
        self::assertIsArray(
            DB::connection()
              ->table((new Casting())->getTable())
              ->where('id', $model->id)
              ->first()->arrayValue,
        );
        self::assertEquals(["Dreamin' 'bout the spot that right now, I'm actually in", 'g-eazy' => 'Still'], $model->arrayValue);

        $model->update(['arrayValue' => ['What if I just said, f*ck it, never followed my dreams?']]);

        self::assertIsArray($model->arrayValue);
        self::assertIsArray(
            DB::connection()
              ->table((new Casting())->getTable())
              ->where('id', $model->id)
              ->first()->arrayValue,
        );
        self::assertEquals(['What if I just said, f*ck it, never followed my dreams?'], $model->arrayValue);
    }

    public function testArrayLegacyJsonStringIsStillReadable(): void
    {
        // Simulate data previously saved with the old behavior (JSON-encoded string).
        $id = new ObjectId();
        $table = (new Casting())->getTable();
        DB::connection()->table($table)->insert([
            '_id' => $id,
            'arrayValue' => '{"key":"value","nested":{"a":1}}',
        ]);

        /** @var Casting $model */
        $model = Casting::query()->find($id);

        self::assertIsArray($model->arrayValue);
        self::assertSame(['key' => 'value', 'nested' => ['a' => 1]], $model->arrayValue);
    }
}
