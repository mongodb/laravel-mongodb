<?php

declare(strict_types=1);

namespace Casts;

use Illuminate\Database\Eloquent\Casts\Json;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Collection;
use MongoDB\Laravel\Tests\Models\Casting;
use MongoDB\Laravel\Tests\TestCase;

class EncryptionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Casting::truncate();
    }

    protected function decryptRaw(Casting $model, $key)
    {
        return app()->make(Encrypter::class)
            ->decryptString(
                $model->getRawOriginal($key)
            );
    }

    public function testEncryptedString(): void
    {
        $model = Casting::query()->create(['encryptedString' => 'encrypted']);

        self::assertIsString($model->encryptedString);
        self::assertEquals('encrypted', $model->encryptedString);
        self::assertNotEquals('encrypted', $model->getRawOriginal('encryptedString'));
        self::assertEquals('encrypted', $this->decryptRaw($model, 'encryptedString'));

        $model->update(['encryptedString' => 'updated']);
        self::assertIsString($model->encryptedString);
        self::assertEquals('updated', $model->encryptedString);
        self::assertNotEquals('updated', $model->getRawOriginal('encryptedString'));
        self::assertEquals('updated', $this->decryptRaw($model, 'encryptedString'));
    }

    public function testEncryptedArray(): void
    {
        $expected = ['foo' => 'bar'];
        $model = Casting::query()->create(['encryptedArray' => $expected]);

        self::assertIsArray($model->encryptedArray);
        self::assertEquals($expected, $model->encryptedArray);
        self::assertNotEquals($expected, $model->getRawOriginal('encryptedArray'));
        self::assertEquals($expected, Json::decode($this->decryptRaw($model, 'encryptedArray')));

        $updated = ['updated' => 'array'];
        $model->update(['encryptedArray' => $updated]);
        self::assertIsArray($model->encryptedArray);
        self::assertEquals($updated, $model->encryptedArray);
        self::assertNotEquals($updated, $model->getRawOriginal('encryptedArray'));
        self::assertEquals($updated, Json::decode($this->decryptRaw($model, 'encryptedArray')));
    }

    public function testEncryptedObject(): void
    {
        $expected = (object) ['foo' => 'bar'];
        $model = Casting::query()->create(['encryptedObject' => $expected]);

        self::assertIsObject($model->encryptedObject);
        self::assertEquals($expected, $model->encryptedObject);
        self::assertNotEquals($expected, $model->getRawOriginal('encryptedObject'));
        self::assertEquals($expected, Json::decode($this->decryptRaw($model, 'encryptedObject'), false));

        $updated = (object) ['updated' => 'object'];
        $model->update(['encryptedObject' => $updated]);
        self::assertIsObject($model->encryptedObject);
        self::assertEquals($updated, $model->encryptedObject);
        self::assertNotEquals($updated, $model->getRawOriginal('encryptedObject'));
        self::assertEquals($updated, Json::decode($this->decryptRaw($model, 'encryptedObject'), false));
    }

    public function testEncryptedCollection(): void
    {
        $expected = collect(['foo' => 'bar']);
        $model = Casting::query()->create(['encryptedCollection' => $expected]);

        self::assertInstanceOf(Collection::class, $model->encryptedCollection);
        self::assertEquals($expected, $model->encryptedCollection);
        self::assertNotEquals($expected, $model->getRawOriginal('encryptedCollection'));
        self::assertEquals($expected, collect(Json::decode($this->decryptRaw($model, 'encryptedCollection'), false)));

        $updated = collect(['updated' => 'object']);
        $model->update(['encryptedCollection' => $updated]);
        self::assertIsObject($model->encryptedCollection);
        self::assertEquals($updated, $model->encryptedCollection);
        self::assertNotEquals($updated, $model->getRawOriginal('encryptedCollection'));
        self::assertEquals($updated, collect(Json::decode($this->decryptRaw($model, 'encryptedCollection'), false)));
    }
}
