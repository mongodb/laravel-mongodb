<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Casts;

use Generator;
use MongoDB\BSON\Binary;
use MongoDB\Laravel\Tests\Models\Casting;
use MongoDB\Laravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use function hex2bin;

class BinaryUuidTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Casting::truncate();
    }

    #[DataProvider('provideBinaryUuidCast')]
    public function testBinaryUuidCastModel(string $expectedUuid, string|Binary $saveUuid, Binary $queryUuid): void
    {
        Casting::create(['uuid' => $saveUuid]);

        $model = Casting::firstWhere('uuid', $queryUuid);
        $this->assertNotNull($model);
        $this->assertSame($expectedUuid, $model->uuid);
    }

    public static function provideBinaryUuidCast(): Generator
    {
        $uuid       = '0c103357-3806-48c9-a84b-867dcb625cfb';
        $binaryUuid = new Binary(hex2bin('0c103357380648c9a84b867dcb625cfb'), Binary::TYPE_UUID);

        yield 'Save Binary, Query Binary' => [$uuid, $binaryUuid, $binaryUuid];
        yield 'Save string, Query Binary' => [$uuid, $uuid, $binaryUuid];
    }

    #[DataProvider('provideUuidString')]
    public function testQueryByStringCastsToBinaryUuid(string $queryUuid): void
    {
        $uuid = '0c103357-3806-48c9-a84b-867dcb625cfb';

        Casting::create(['uuid' => $uuid]);

        $model = Casting::firstWhere('uuid', $queryUuid);
        $this->assertNotNull($model);
        $this->assertSame($uuid, $model->uuid);

        $models = Casting::whereIn('uuid', [$queryUuid, '11111111-2222-3333-4444-555555555555'])->get();
        $this->assertCount(1, $models);
        $this->assertSame($uuid, $models->first()->uuid);
    }

    public static function provideUuidString(): Generator
    {
        yield 'canonical' => ['0c103357-3806-48c9-a84b-867dcb625cfb'];
        yield 'uppercase' => ['0C103357-3806-48C9-A84B-867DCB625CFB'];
        yield 'without dashes' => ['0c103357380648c9a84b867dcb625cfb'];
    }

    #[DataProvider('provideNonUuidQueryValue')]
    public function testQueryByNonUuidValueIsNotConverted(mixed $value): void
    {
        Casting::create(['uuid' => '0c103357-3806-48c9-a84b-867dcb625cfb']);

        // A value that cannot be a UUID is used as-is and does not throw
        $this->assertNull(Casting::firstWhere('uuid', $value));
        $this->assertSame(1, Casting::where('uuid', '!=', $value)->count());
    }

    public static function provideNonUuidQueryValue(): Generator
    {
        yield 'invalid string' => ['not-a-uuid'];
        yield 'inconsistent dashes' => ['0c103357-380648c9-a84b-867dcb625cfb'];
        yield 'too short' => ['0c103357-3806-48c9-a84b-867dcb625cf'];
        yield 'non hexadecimal' => ['0c103357-3806-48c9-a84b-867dcb625cfg'];
        yield 'integer' => [12345];
        yield 'null' => [null];
    }
}
