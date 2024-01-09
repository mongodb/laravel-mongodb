<?php

declare(strict_types=1);

namespace Casts;

use Illuminate\Encryption\Encrypter;
use MongoDB\Laravel\Tests\Models\Casting;
use MongoDB\Laravel\Tests\TestCase;

class EncryptionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Casting::truncate();
    }

    public function testEncryptedString(): void
    {
        $model = Casting::query()->create(['encryptedString' => 'encrypted']);

        self::assertIsString($model->encryptedString);
        self::assertEquals('encrypted', $model->encryptedString);
        self::assertNotEquals('encrypted', $model->getRawOriginal('encryptedString'));
        self::assertEquals('encrypted', app()->make(Encrypter::class)->decryptString($model->getRawOriginal('encryptedString')));
    }
}
