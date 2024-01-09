<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Casts;

use Carbon\CarbonImmutable;
use DateTime;
use Illuminate\Support\Carbon;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Laravel\Tests\Models\Casting;
use MongoDB\Laravel\Tests\TestCase;

use function now;

class DatetimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(now());
        Casting::truncate();
    }

    public function testDatetime(): void
    {
        $model = Casting::query()->create(['datetimeField' => now()]);

        self::assertInstanceOf(Carbon::class, $model->datetimeField);
        self::assertInstanceOf(UTCDateTime::class, $model->getRawOriginal('datetimeField'));
        self::assertEquals(now()->format('Y-m-d H:i:s'), (string) $model->datetimeField);

        $model->update(['datetimeField' => now()->subDay()]);

        self::assertInstanceOf(Carbon::class, $model->datetimeField);
        self::assertInstanceOf(UTCDateTime::class, $model->getRawOriginal('datetimeField'));
        self::assertEquals(now()->subDay()->format('Y-m-d H:i:s'), (string) $model->datetimeField);
    }

    public function testDatetimeAsString(): void
    {
        $model = Casting::query()->create(['datetimeField' => '2023-10-29']);

        self::assertInstanceOf(Carbon::class, $model->datetimeField);
        self::assertInstanceOf(UTCDateTime::class, $model->getRawOriginal('datetimeField'));
        self::assertEquals(
            Carbon::createFromTimestamp(1698577443)->startOfDay()->format('Y-m-d H:i:s'),
            (string) $model->datetimeField,
        );

        $model->update(['datetimeField' => '2023-10-28 11:04:03']);

        self::assertInstanceOf(Carbon::class, $model->datetimeField);
        self::assertInstanceOf(UTCDateTime::class, $model->getRawOriginal('datetimeField'));
        self::assertEquals(
            Carbon::createFromTimestamp(1698577443)->subDay()->format('Y-m-d H:i:s'),
            (string) $model->datetimeField,
        );
    }

    public function testDatetimeWithCustomFormat(): void
    {
        $model = Casting::query()->create(['datetimeWithFormatField' => DateTime::createFromInterface(now())]);

        self::assertInstanceOf(Carbon::class, $model->datetimeWithFormatField);
        self::assertEquals(now()->format('j.n.Y H:i'), (string) $model->datetimeWithFormatField);

        $model->update(['datetimeWithFormatField' => now()->subDay()]);

        self::assertInstanceOf(Carbon::class, $model->datetimeWithFormatField);
        self::assertEquals(now()->subDay()->format('j.n.Y H:i'), (string) $model->datetimeWithFormatField);
    }

    public function testImmutableDatetime(): void
    {
        $model = Casting::query()->create(['immutableDatetimeField' => DateTime::createFromInterface(now())]);

        self::assertInstanceOf(CarbonImmutable::class, $model->immutableDatetimeField);
        self::assertEquals(now()->format('Y-m-d H:i:s'), (string) $model->immutableDatetimeField);

        $model->update(['immutableDatetimeField' => now()->subDay()]);

        self::assertInstanceOf(CarbonImmutable::class, $model->immutableDatetimeField);
        self::assertEquals(now()->subDay()->format('Y-m-d H:i:s'), (string) $model->immutableDatetimeField);

        $model->update(['immutableDatetimeField' => '2023-10-28 11:04:03']);

        self::assertInstanceOf(CarbonImmutable::class, $model->immutableDatetimeField);
        self::assertInstanceOf(UTCDateTime::class, $model->getRawOriginal('immutableDatetimeField'));
        self::assertEquals(
            Carbon::createFromTimestamp(1698577443)->subDay()->format('Y-m-d H:i:s'),
            (string) $model->immutableDatetimeField,
        );
    }

    public function testImmutableDatetimeWithCustomFormat(): void
    {
        $model = Casting::query()->create(['immutableDatetimeWithFormatField' => DateTime::createFromInterface(now())]);

        self::assertInstanceOf(CarbonImmutable::class, $model->immutableDatetimeWithFormatField);
        self::assertEquals(now()->format('j.n.Y H:i'), (string) $model->immutableDatetimeWithFormatField);

        $model->update(['immutableDatetimeWithFormatField' => now()->subDay()]);

        self::assertInstanceOf(CarbonImmutable::class, $model->immutableDatetimeWithFormatField);
        self::assertEquals(now()->subDay()->format('j.n.Y H:i'), (string) $model->immutableDatetimeWithFormatField);

        $model->update(['immutableDatetimeWithFormatField' => '2023-10-28 11:04:03']);

        self::assertInstanceOf(CarbonImmutable::class, $model->immutableDatetimeWithFormatField);
        self::assertEquals(
            Carbon::createFromTimestamp(1698577443)->subDay()->format('j.n.Y H:i'),
            (string) $model->immutableDatetimeWithFormatField,
        );
    }
}
