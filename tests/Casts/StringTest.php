<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Casts;

use MongoDB\Laravel\Tests\Models\Casting;
use MongoDB\Laravel\Tests\TestCase;

class StringTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Casting::truncate();
    }

    public function testString(): void
    {
        $model = Casting::query()->create(['stringContent' => "If I'ma shoot, I shoot to kill"]);

        self::assertIsString($model->stringContent);
        self::assertEquals("If I'ma shoot, I shoot to kill", $model->stringContent);

        $model->update(['stringContent' => 'Do what I want to do at will']);

        self::assertIsString($model->stringContent);
        self::assertEquals('Do what I want to do at will', $model->stringContent);
    }
}
