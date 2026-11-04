<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Casts;

use Generator;
use Illuminate\Support\Facades\DB;
use MongoDB\Laravel\Tests\Models\CastAsBinaryUuid;
use MongoDB\Laravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use function hex2bin;

class AsBinaryUuidTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CastAsBinaryUuid::truncate();
    }

    #[DataProvider('provideUuidString')]
    public function testQueryByStringCastsToBinaryUuid(string $queryUuid): void
    {
        $uuid = '0c103357-3806-48c9-a84b-867dcb625cfb';

        CastAsBinaryUuid::create(['uuid' => $uuid]);

        $model = CastAsBinaryUuid::firstWhere('uuid', $queryUuid);
        $this->assertNotNull($model);
        $this->assertSame($uuid, $model->uuid);

        $models = CastAsBinaryUuid::whereIn('uuid', [$queryUuid, '11111111-2222-3333-4444-555555555555'])->get();
        $this->assertCount(1, $models);
        $this->assertSame($uuid, $models->first()->uuid);
    }

    public static function provideUuidString(): Generator
    {
        yield 'canonical' => ['0c103357-3806-48c9-a84b-867dcb625cfb'];
        yield 'uppercase' => ['0C103357-3806-48C9-A84B-867DCB625CFB'];
        yield 'without dashes' => ['0c103357380648c9a84b867dcb625cfb'];
    }

    public function testQueryByRawBytesCastsToBinaryUuid(): void
    {
        $uuid     = '0c103357-3806-48c9-a84b-867dcb625cfb';
        $rawBytes = hex2bin('0c103357380648c9a84b867dcb625cfb');

        CastAsBinaryUuid::create(['uuid' => $uuid]);

        $model = CastAsBinaryUuid::firstWhere('uuid', $rawBytes);
        $this->assertNotNull($model);
        $this->assertSame($uuid, $model->uuid);
    }

    public function testQueryByPrintableStringIsNotConverted(): void
    {
        // A 16-character printable string is a legacy string value, it must not become a UUID Binary.
        // Stored outside the model, as query-builder writes bypass casts.
        $token = 'sessiontoken1234';

        DB::connection('mongodb')->getCollection('cast_as_binary_uuids')->insertOne(['uuid' => $token]);

        $model = CastAsBinaryUuid::firstWhere('uuid', $token);
        $this->assertNotNull($model);
        $this->assertSame($token, $model->uuid);
    }

    #[DataProvider('provideNonUuidQueryValue')]
    public function testQueryByNonUuidValueIsNotConverted(mixed $value): void
    {
        CastAsBinaryUuid::create(['uuid' => '0c103357-3806-48c9-a84b-867dcb625cfb']);

        // A value that cannot be a UUID is used as-is and does not throw
        $this->assertNull(CastAsBinaryUuid::firstWhere('uuid', $value));
        $this->assertSame(1, CastAsBinaryUuid::where('uuid', '!=', $value)->count());
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
