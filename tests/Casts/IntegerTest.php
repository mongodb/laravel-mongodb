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
        $check = Casting::query()->find($model->_id);

        self::assertIsInt($check->intNumber);
        self::assertEquals(1,$check->intNumber);

        $model->update(['intNumber' => 2]);
        $check = Casting::query()->find($model->_id);

        self::assertIsInt($check->intNumber);
        self::assertEquals(2,$check->intNumber);
    }

    public function testIntAsString(): void
    {
        $model = Casting::query()->create(['intNumber' => '1']);
        $check = Casting::query()->find($model->_id);

        self::assertIsInt($check->intNumber);
        self::assertEquals(1,$check->intNumber);

        $model->update(['intNumber' => '2']);
        $check = Casting::query()->find($model->_id);

        self::assertIsInt($check->intNumber);
        self::assertEquals(2,$check->intNumber);
    }
}
