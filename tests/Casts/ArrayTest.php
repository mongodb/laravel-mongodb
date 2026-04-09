<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Casts;

use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;
use MongoDB\Laravel\Tests\Models\Casting;
use MongoDB\Laravel\Tests\TestCase;
use stdClass;

use function assert;
use function restore_error_handler;
use function set_error_handler;

use const E_USER_DEPRECATED;

class ArrayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Casting::truncate();
    }

    public function testArrayCastTriggersDeprecation(): void
    {
        $deprecations = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$deprecations): bool {
            if ($errno === E_USER_DEPRECATED) {
                $deprecations[] = $errstr;

                return true;
            }

            return false;
        });

        try {
            $model = Casting::query()->create(['arrayValue' => ['key' => 'value']]);
            assert($model instanceof Casting);
        } finally {
            restore_error_handler();
        }

        self::assertNotEmpty($deprecations);
        self::assertStringContainsString('Remove the cast to store native BSON arrays.', $deprecations[0]);
        self::assertIsArray($model->arrayValue);
        self::assertSame(['key' => 'value'], $model->arrayValue);
    }

    public function testJsonStringIsNotDecodedWithoutCast(): void
    {
        // Document stored with the old behavior (JSON-encoded string via "array" cast).
        $id = new ObjectId();
        DB::connection()->table((new Casting())->getTable())->insert([
            '_id' => $id,
            'arrayValue' => '{"key":"value"}',
        ]);

        // Without a cast, the raw JSON string is returned as-is — not decoded.
        // Users must migrate their data before removing the "array" cast.
        $raw = DB::connection()->table((new Casting())->getTable())->find($id);
        assert($raw instanceof stdClass);

        self::assertIsString($raw->arrayValue);
        self::assertSame('{"key":"value"}', $raw->arrayValue);
    }

    public function testArrayCastStillReadableAsBsonNativeArray(): void
    {
        // A value stored as a native BSON array (e.g. by another system or after a future migration)
        // must remain readable via the "array" cast.
        $id = new ObjectId();
        DB::connection()->table((new Casting())->getTable())->insert([
            '_id' => $id,
            'arrayValue' => ['key' => 'value', 'nested' => ['a' => 1]],
        ]);

        $model = Casting::query()->find($id);
        assert($model instanceof Casting);

        self::assertIsArray($model->arrayValue);
        self::assertSame(['key' => 'value', 'nested' => ['a' => 1]], $model->arrayValue);
    }

    public function testObjectCastWithNativeBsonDocumentReturnsObject(): void
    {
        // A native BSON document read via an "object" cast must come back as an object, not an array.
        $id = new ObjectId();
        DB::connection()->table((new Casting())->getTable())->insert([
            '_id' => $id,
            'objectValue' => ['key' => 'value'],
        ]);

        $model = Casting::query()->find($id);
        assert($model instanceof Casting);

        self::assertIsObject($model->objectValue);
        self::assertSame('value', $model->objectValue->key);
    }
}
