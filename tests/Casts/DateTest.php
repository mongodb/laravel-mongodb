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
        self::assertInstanceOf(UTCDateTime::class, $model->getRawOriginal('dateField'));
        self::assertEquals(now()->subDay()->startOfDay()->format('Y-m-d H:i:s'), (string) $model->dateField);

        $model->update(['dateField' => new DateTime()]);

        self::assertInstanceOf(Carbon::class, $model->dateField);
        self::assertInstanceOf(UTCDateTime::class, $model->getRawOriginal('dateField'));
        self::assertEquals(now()->startOfDay()->format('Y-m-d H:i:s'), (string) $model->dateField);

        $model->update(['dateField' => (new DateTime())->modify('-1 day')]);

        self::assertInstanceOf(Carbon::class, $model->dateField);
        self::assertInstanceOf(UTCDateTime::class, $model->getRawOriginal('dateField'));
        self::assertEquals(now()->subDay()->startOfDay()->format('Y-m-d H:i:s'), (string) $model->dateField);

        $refetchedModel = Casting::query()->find($model->getKey());

        self::assertInstanceOf(Carbon::class, $refetchedModel->dateField);
        self::assertInstanceOf(UTCDateTime::class, $model->getRawOriginal('dateField'));
        self::assertEquals(now()->subDay()->startOfDay()->format('Y-m-d H:i:s'), (string) $refetchedModel->dateField);

        $model = Casting::query()->create();
        $this->assertNull($model->dateField);

        $model->update(['dateField' => null]);
        $this->assertNull($model->dateField);
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

    public function testImmutableDateWithCustomFormat(): void
    {
        // With a custom format, "date" cast behave like "datetime". The time is not reset to the start of the day.
        // https://github.com/laravel/framework/blob/v10.46.0/src/Illuminate/Database/Eloquent/Concerns/HasAttributes.php#L866-L869
        $model = Casting::query()->create(['immutableDateWithFormatField' => new DateTime()]);

        self::assertInstanceOf(CarbonImmutable::class, $model->immutableDateWithFormatField);
        self::assertEquals(now()->format('j.n.Y H:i'), (string) $model->immutableDateWithFormatField);

        $model->update(['immutableDateWithFormatField' => now()->startOfDay()->subDay()]);

        self::assertInstanceOf(CarbonImmutable::class, $model->immutableDateWithFormatField);
        self::assertEquals(now()->startOfDay()->subDay()->format('j.n.Y H:i'), (string) $model->immutableDateWithFormatField);

        $model->update(['immutableDateWithFormatField' => '2023-10-28']);

        self::assertInstanceOf(CarbonImmutable::class, $model->immutableDateWithFormatField);
        self::assertEquals(
            Carbon::createFromTimestamp(1698577443)->subDay()->startOfDay()->format('j.n.Y H:i'),
            (string) $model->immutableDateWithFormatField,
        );

        $model = Casting::query()->create();
        $this->assertNull($model->immutableDateField);

        $model->update(['immutableDateField' => null]);
        $this->assertNull($model->immutableDateField);
    }
}
