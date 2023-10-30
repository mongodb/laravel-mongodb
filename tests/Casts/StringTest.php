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
        $model = Casting::query()->create(['stringContent' => 'Home is behind The world ahead And there are many paths to tread']);

        self::assertIsString($model->stringContent);
        self::assertEquals('Home is behind The world ahead And there are many paths to tread', $model->stringContent);

        $model->update(['stringContent' => "Losing hope, don't mean I'm hopeless And maybe all I need is time"]);

        self::assertIsString($model->stringContent);
        self::assertEquals("Losing hope, don't mean I'm hopeless And maybe all I need is time", $model->stringContent);
    }
}
