<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Casts;

use MongoDB\Laravel\Tests\Models\Casting;
use MongoDB\Laravel\Tests\TestCase;

class ObjectTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Casting::truncate();
    }

    public function testObject(): void
    {
        $model = Casting::query()->create(['objectValue' => ['g' => 'G-Eazy']]);

        self::assertIsObject($model->objectValue);
        self::assertEquals((object)['g' => 'G-Eazy'],$model->objectValue);

        $model->update(['objectValue' => ['Dont let me go' => 'Even the brightest of colors turn greys']]);
        $check = Casting::query()->find($model->_id);

        self::assertIsObject($check->objectValue);
        self::assertEquals((object)['Dont let me go' => 'Even the brightest of colors turn greys'],$check->objectValue);
    }
}
