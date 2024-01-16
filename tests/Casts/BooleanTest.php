<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Casts;

use MongoDB\Laravel\Tests\Models\Casting;
use MongoDB\Laravel\Tests\TestCase;

class BooleanTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Casting::truncate();
    }

    public function testBool(): void
    {
        $model = Casting::query()->create(['booleanValue' => true]);

        self::assertIsBool($model->booleanValue);
        self::assertSame(true, $model->booleanValue);

        $model->update(['booleanValue' => false]);

        self::assertIsBool($model->booleanValue);
        self::assertSame(false, $model->booleanValue);

        $model->update(['booleanValue' => 1]);

        self::assertIsBool($model->booleanValue);
        self::assertSame(true, $model->booleanValue);

        $model->update(['booleanValue' => 0]);

        self::assertIsBool($model->booleanValue);
        self::assertSame(false, $model->booleanValue);
    }

    public function testBoolAsString(): void
    {
        $model = Casting::query()->create(['booleanValue' => '1.79']);

        self::assertIsBool($model->booleanValue);
        self::assertSame(true, $model->booleanValue);

        $model->update(['booleanValue' => '0']);

        self::assertIsBool($model->booleanValue);
        self::assertSame(false, $model->booleanValue);

        $model->update(['booleanValue' => 'false']);

        self::assertIsBool($model->booleanValue);
        self::assertSame(true, $model->booleanValue);

        $model->update(['booleanValue' => '0.0']);

        self::assertIsBool($model->booleanValue);
        self::assertSame(true, $model->booleanValue);

        $model->update(['booleanValue' => 'true']);

        self::assertIsBool($model->booleanValue);
        self::assertSame(true, $model->booleanValue);
    }

    public function testBoolAsNumber(): void
    {
        $model = Casting::query()->create(['booleanValue' => 1]);

        self::assertIsBool($model->booleanValue);
        self::assertSame(true, $model->booleanValue);

        $model->update(['booleanValue' => 0]);

        self::assertIsBool($model->booleanValue);
        self::assertSame(false, $model->booleanValue);

        $model->update(['booleanValue' => 1.79]);

        self::assertIsBool($model->booleanValue);
        self::assertSame(true, $model->booleanValue);

        $model->update(['booleanValue' => 0.0]);
        self::assertIsBool($model->booleanValue);
        self::assertSame(false, $model->booleanValue);
    }
}
