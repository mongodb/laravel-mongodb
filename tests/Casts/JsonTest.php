<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Casts;

use MongoDB\Laravel\Tests\Models\Casting;
use MongoDB\Laravel\Tests\TestCase;

class JsonTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Casting::truncate();
    }

    public function testJson(): void
    {
        $model = Casting::query()->create(['jsonValue' => ['g' => 'G-Eazy']]);
        $check = Casting::query()->find($model->_id);

        self::assertIsArray($check->jsonValue);
        self::assertEquals(['g' => 'G-Eazy'],$check->jsonValue);

        $model->update(['jsonValue' => ['Dont let me go' => 'Even the longest of nights turn days']]);
        $check = Casting::query()->find($model->_id);

        self::assertIsArray($check->jsonValue);
        self::assertEquals(['Dont let me go' => 'Even the longest of nights turn days'],$check->jsonValue);
    }
}
