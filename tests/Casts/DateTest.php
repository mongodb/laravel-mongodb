<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Casts;

use Carbon\CarbonImmutable;
use DateTime;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Tests\Models\Casting;
use MongoDB\Laravel\Tests\TestCase;

use function now;

class DateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Casting::truncate();
    }

    public function testDate(): void
    {
        $model = Casting::query()->create(['dateField' => now()]);

        self::assertInstanceOf(Carbon::class, $model->dateField);
        self::assertEquals(now()->startOfDay()->format('Y-m-d H:i:s'), (string) $model->dateField);

        $model->update(['dateField' => now()->subDay()]);

        self::assertInstanceOf(Carbon::class, $model->dateField);
        self::assertEquals(now()->subDay()->startOfDay()->format('Y-m-d H:i:s'), (string) $model->dateField);

        $model->update(['dateField' => new DateTime()]);

        self::assertInstanceOf(Carbon::class, $model->dateField);
        self::assertEquals(now()->startOfDay()->format('Y-m-d H:i:s'), (string) $model->dateField);

        $model->update(['dateField' => (new DateTime())->modify('-1 day')]);

        self::assertInstanceOf(Carbon::class, $model->dateField);
        self::assertEquals(now()->subDay()->startOfDay()->format('Y-m-d H:i:s'), (string) $model->dateField);
    }

    public function testDateAsString(): void
    {
        $model = Casting::query()->create(['dateField' => '2023-10-29']);

        self::assertInstanceOf(Carbon::class, $model->dateField);
        self::assertEquals(
            Carbon::createFromTimestamp(1698577443)->startOfDay()->format('Y-m-d H:i:s'),
            (string) $model->dateField,
        );

        $model->update(['dateField' => '2023-10-28']);

        self::assertInstanceOf(Carbon::class, $model->dateField);
        self::assertEquals(
            Carbon::createFromTimestamp(1698577443)->subDay()->startOfDay()->format('Y-m-d H:i:s'),
            (string) $model->dateField,
        );
    }

    public function testDateWithCustomFormat(): void
    {
        $model = Casting::query()->create(['dateWithFormatField' => new DateTime()]);

        self::assertInstanceOf(Carbon::class, $model->dateWithFormatField);
        self::assertEquals(now()->startOfDay()->format('j.n.Y H:i'), (string) $model->dateWithFormatField);

        $model->update(['dateWithFormatField' => now()->subDay()]);

        self::assertInstanceOf(Carbon::class, $model->dateWithFormatField);
        self::assertEquals(now()->startOfDay()->subDay()->format('j.n.Y H:i'), (string) $model->dateWithFormatField);
    }

    public function testImmutableDate(): void
    {
        $model = Casting::query()->create(['immutableDateField' => new DateTime()]);

        self::assertInstanceOf(CarbonImmutable::class, $model->immutableDateField);
        self::assertEquals(now()->startOfDay()->format('Y-m-d H:i:s'), (string) $model->immutableDateField);

        $model->update(['immutableDateField' => now()->subDay()]);

        self::assertInstanceOf(CarbonImmutable::class, $model->immutableDateField);
        self::assertEquals(now()->startOfDay()->subDay()->format('Y-m-d H:i:s'), (string) $model->immutableDateField);

        $model->update(['immutableDateField' => '2023-10-28']);

        self::assertInstanceOf(CarbonImmutable::class, $model->immutableDateField);
        self::assertEquals(
            Carbon::createFromTimestamp(1698577443)->subDay()->startOfDay()->format('Y-m-d H:i:s'),
            (string) $model->immutableDateField,
        );
    }
}
