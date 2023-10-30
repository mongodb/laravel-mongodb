<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Casts;

use MongoDB\Laravel\Tests\Models\Casting;
use MongoDB\Laravel\Tests\TestCase;

class IntegerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Casting::truncate();
    }

    public function testInt(): void
    {
        $model = Casting::query()->create(['intNumber' => 1]);

        self::assertIsInt($model->intNumber);
        self::assertEquals(1, $model->intNumber);

        $model->update(['intNumber' => 2]);

        self::assertIsInt($model->intNumber);
        self::assertEquals(2, $model->intNumber);

        $model->update(['intNumber' => 9.6]);

        self::assertIsInt($model->intNumber);
        self::assertEquals(9, $model->intNumber);
    }

    public function testIntAsString(): void
    {
        $model = Casting::query()->create(['intNumber' => '1']);

        self::assertIsInt($model->intNumber);
        self::assertEquals(1, $model->intNumber);

        $model->update(['intNumber' => '2']);

        self::assertIsInt($model->intNumber);
        self::assertEquals(2, $model->intNumber);

        $model->update(['intNumber' => '9.6']);

        self::assertIsInt($model->intNumber);
        self::assertEquals(9, $model->intNumber);
    }
}
