<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Casts;

use DateTime;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Tests\Models\Casting;
use MongoDB\Laravel\Tests\TestCase;

use function now;

class DatetimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Casting::truncate();
    }

    public function testDatetime(): void
    {
        $model = Casting::query()->create(['datetimeField' => now()]);

        self::assertInstanceOf(Carbon::class, $model->datetimeField);
        self::assertEquals(now()->format('Y-m-d H:i:s'), (string) $model->datetimeField);

        $model->update(['datetimeField' => now()->subDay()]);

        self::assertInstanceOf(Carbon::class, $model->datetimeField);
        self::assertEquals(now()->subDay()->format('Y-m-d H:i:s'), (string) $model->datetimeField);
    }

    public function testDatetimeAsString(): void
    {
        $model = Casting::query()->create(['datetimeField' => '2023-10-29']);

        self::assertInstanceOf(Carbon::class, $model->datetimeField);
        self::assertEquals(
            Carbon::createFromTimestamp(1698577443)->startOfDay()->format('Y-m-d H:i:s'),
            (string) $model->datetimeField,
        );

        $model->update(['datetimeField' => '2023-10-28 11:04:03']);

        self::assertInstanceOf(Carbon::class, $model->datetimeField);
        self::assertEquals(
            Carbon::createFromTimestamp(1698577443)->subDay()->format('Y-m-d H:i:s'),
            (string) $model->datetimeField,
        );
    }

    public function testDatetimeWithCustomFormat(): void
    {
        $model = Casting::query()->create(['datetimeWithFormatField' => new DateTime()]);

        self::assertInstanceOf(Carbon::class, $model->datetimeWithFormatField);
        self::assertEquals(now()->format('j.n.Y H:i'), (string) $model->datetimeWithFormatField);

        $model->update(['datetimeWithFormatField' => now()->subDay()]);

        self::assertInstanceOf(Carbon::class, $model->datetimeWithFormatField);
        self::assertEquals(now()->subDay()->format('j.n.Y H:i'), (string) $model->datetimeWithFormatField);
    }

}
