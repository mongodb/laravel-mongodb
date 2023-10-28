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
        $model = Casting::query()->create(['number' => 1]);
        $check = Casting::query()->find($model->_id);

        self::assertIsInt($check->number);
        self::assertEquals(1,$check->number);

        $model->update(['number' => 2]);
        $check = Casting::query()->find($model->_id);

        self::assertIsInt($check->number);
        self::assertEquals(2,$check->number);
    }
    /**
     * @return void
     * @group hans
     */
    public function testIntAsString(): void
    {
        $model = Casting::query()->create(['number' => '1']);

        $check = Casting::query()->find($model->_id);
        self::assertIsInt($check->number);
        self::assertEquals(1,$check->number);

        $model->update(['number' => '1b']);

        $check = Casting::query()->find($model->_id);
        self::assertIsInt($check->number);
        self::assertEquals(1,$check->number);

        $model->update(['number' => 'a1b']);

        $check = Casting::query()->find($model->_id);
        self::assertIsInt($check->number);
        self::assertEquals(0,$check->number);
    }
}
