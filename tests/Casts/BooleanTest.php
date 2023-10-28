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
        $check = Casting::query()->find($model->_id);

        self::assertIsBool($check->booleanValue);
        self::assertEquals(true,$check->booleanValue);

        $model->update(['booleanValue' => false]);
        $check = Casting::query()->find($model->_id);

        self::assertIsBool($check->booleanValue);
        self::assertEquals(false,$check->booleanValue);

        $model->update(['booleanValue' => 1]);
        $check = Casting::query()->find($model->_id);

        self::assertIsBool($check->booleanValue);
        self::assertEquals(true,$check->booleanValue);

        $model->update(['booleanValue' => 0]);
        $check = Casting::query()->find($model->_id);

        self::assertIsBool($check->booleanValue);
        self::assertEquals(false,$check->booleanValue);
    }

    public function testBoolAsString(): void
    {
        $model = Casting::query()->create(['booleanValue' => '1.79']);
        $check = Casting::query()->find($model->_id);

        self::assertIsBool($check->booleanValue);
        self::assertEquals(true,$check->booleanValue);

        $model->update(['booleanValue' => '0']);
        $check = Casting::query()->find($model->_id);

        self::assertIsBool($check->booleanValue);
        self::assertEquals(false,$check->booleanValue);
    }
}
