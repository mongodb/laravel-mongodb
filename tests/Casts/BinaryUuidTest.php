<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Casts;

use Generator;
use MongoDB\BSON\Binary;
use MongoDB\Laravel\Tests\Models\CastBinaryUuid;
use MongoDB\Laravel\Tests\TestCase;

use function hex2bin;

class BinaryUuidTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CastBinaryUuid::truncate();
    }

    /** @dataProvider provideBinaryUuidCast */
    public function testBinaryUuidCastModel(string $expectedUuid, string|Binary $saveUuid, Binary $queryUuid): void
    {
        CastBinaryUuid::create(['uuid' => $saveUuid]);

        $model = CastBinaryUuid::firstWhere('uuid', $queryUuid);
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

    public function testQueryByStringDoesNotCast(): void
    {
        $uuid = '0c103357-3806-48c9-a84b-867dcb625cfb';

        CastBinaryUuid::create(['uuid' => $uuid]);

        $model = CastBinaryUuid::firstWhere('uuid', $uuid);
        $this->assertNull($model);
    }
}
