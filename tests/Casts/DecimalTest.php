<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Casts;

use MongoDB\BSON\Decimal128;
use MongoDB\Laravel\Tests\Models\Casting;
use MongoDB\Laravel\Tests\TestCase;

class DecimalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Casting::truncate();
    }

    public function testDecimal(): void
    {
        $model = Casting::query()->create(['decimalNumber' => 100.99]);

        self::assertInstanceOf(Decimal128::class,$model->decimalNumber);
        self::assertEquals('100.99',$model->decimalNumber);

        $model->update(['decimalNumber' => 9999.9]);
        $check = Casting::query()->find($model->_id);

        self::assertInstanceOf(Decimal128::class,$model->decimalNumber);
        self::assertEquals('9999.90',$check->decimalNumber);
    }

    public function testDecimalAsString(): void
    {
        $model = Casting::query()->create(['decimalNumber' => '120.79']);

        self::assertInstanceOf(Decimal128::class,$model->decimalNumber);
        self::assertEquals('120.79',$model->decimalNumber);

        $model->update(['decimalNumber' => '795']);
        $check = Casting::query()->find($model->_id);

        self::assertInstanceOf(Decimal128::class,$model->decimalNumber);
        self::assertEquals('795.00',$check->decimalNumber);
    }
}
