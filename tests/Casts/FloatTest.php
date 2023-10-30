<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Casts;

use MongoDB\Laravel\Tests\Models\Casting;
use MongoDB\Laravel\Tests\TestCase;

class FloatTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Casting::truncate();
    }

    public function testFloat(): void
    {
        $model = Casting::query()->create(['floatNumber' => 1.79]);

        self::assertIsFloat($model->floatNumber);
        self::assertEquals(1.79, $model->floatNumber);

        $model->update(['floatNumber' => 7E-5]);

        self::assertIsFloat($model->floatNumber);
        self::assertEquals(7E-5, $model->floatNumber);
    }

    public function testFloatAsString(): void
    {
        $model = Casting::query()->create(['floatNumber' => '1.79']);

        self::assertIsFloat($model->floatNumber);
        self::assertEquals(1.79, $model->floatNumber);

        $model->update(['floatNumber' => '7E-5']);

        self::assertIsFloat($model->floatNumber);
        self::assertEquals(7E-5, $model->floatNumber);
    }
}
