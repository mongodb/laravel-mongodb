<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Casts;

use Illuminate\Support\Carbon;
use MongoDB\Laravel\Tests\Models\Casting;
use MongoDB\Laravel\Tests\TestCase;

/** @group hans */
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
        $check = Casting::query()->find($model->_id);

        self::assertInstanceOf(Carbon::class,$check->dateField);
        self::assertEquals(now()->startOfDay()->format('Y-m-d H:i:s'),(string)$check->dateField);

        $model->update(['dateField' => now()->subDay()]);
        $check = Casting::query()->find($model->_id);

        self::assertInstanceOf(Carbon::class,$check->dateField);
        self::assertEquals(now()->subDay()->startOfDay()->format('Y-m-d H:i:s'),(string)$check->dateField);
    }

    public function testDateAsString(): void
    {
        $model = Casting::query()->create(['dateField' => '2023-10-29']);
        $check = Casting::query()->find($model->_id);

        self::assertInstanceOf(Carbon::class,$check->dateField);
        self::assertEquals(
            Carbon::createFromTimestamp(1698577443)->startOfDay()->format('Y-m-d H:i:s'),
            (string)$check->dateField
        );

        $model->update(['dateField' => '2023-10-28']);
        $check = Casting::query()->find($model->_id);

        self::assertInstanceOf(Carbon::class,$check->dateField);
        self::assertEquals(
            Carbon::createFromTimestamp(1698577443)->subDay()->startOfDay()->format('Y-m-d H:i:s'),
            (string)$check->dateField
        );
    }
}
