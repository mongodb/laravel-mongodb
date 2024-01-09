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

        self::assertIsString($model->decimalNumber);
        self::assertInstanceOf(Decimal128::class, $model->getRawOriginal('decimalNumber'));
        self::assertEquals('100.99', $model->decimalNumber);

        $model->update(['decimalNumber' => 9999.9]);

        self::assertIsString($model->decimalNumber);
        self::assertInstanceOf(Decimal128::class, $model->getRawOriginal('decimalNumber'));
        self::assertEquals('9999.90', $model->decimalNumber);

        $model->update(['decimalNumber' => 9999.00000009]);

        self::assertIsString($model->decimalNumber);
        self::assertInstanceOf(Decimal128::class, $model->getRawOriginal('decimalNumber'));
        self::assertEquals('9999.00', $model->decimalNumber);
    }

    public function testDecimalAsString(): void
    {
        $model = Casting::query()->create(['decimalNumber' => '120.79']);

        self::assertIsString($model->decimalNumber);
        self::assertInstanceOf(Decimal128::class, $model->getRawOriginal('decimalNumber'));
        self::assertEquals('120.79', $model->decimalNumber);

        $model->update(['decimalNumber' => '795']);

        self::assertIsString($model->decimalNumber);
        self::assertInstanceOf(Decimal128::class, $model->getRawOriginal('decimalNumber'));
        self::assertEquals('795.00', $model->decimalNumber);

        $model->update(['decimalNumber' => '1234.99999999999']);

        self::assertIsString($model->decimalNumber);
        self::assertInstanceOf(Decimal128::class, $model->getRawOriginal('decimalNumber'));
        self::assertEquals('1235.00', $model->decimalNumber);
    }

    public function testDecimalAsDecimal128(): void
    {
        $model = Casting::query()->create(['decimalNumber' => new Decimal128('100.99')]);

        self::assertIsString($model->decimalNumber);
        self::assertInstanceOf(Decimal128::class, $model->getRawOriginal('decimalNumber'));
        self::assertEquals('100.99', $model->decimalNumber);

        $model->update(['decimalNumber' => new Decimal128('9999.9')]);

        self::assertIsString($model->decimalNumber);
        self::assertInstanceOf(Decimal128::class, $model->getRawOriginal('decimalNumber'));
        self::assertEquals('9999.90', $model->decimalNumber);
    }
}
