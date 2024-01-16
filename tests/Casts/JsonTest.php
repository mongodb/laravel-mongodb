<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Casts;

use MongoDB\Laravel\Tests\Models\Casting;
use MongoDB\Laravel\Tests\TestCase;

use function json_encode;

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

        self::assertIsArray($model->jsonValue);
        self::assertEquals(['g' => 'G-Eazy'], $model->jsonValue);

        $model->update(['jsonValue' => ['Dont let me go' => 'Even the longest of nights turn days']]);

        self::assertIsArray($model->jsonValue);
        self::assertIsString($model->getRawOriginal('jsonValue'));
        self::assertEquals(['Dont let me go' => 'Even the longest of nights turn days'], $model->jsonValue);

        $json = json_encode(['it will encode json' => 'even if it is already json']);
        $model->update(['jsonValue' => $json]);

        self::assertIsString($model->jsonValue);
        self::assertIsString($model->getRawOriginal('jsonValue'));
        self::assertEquals($json, $model->jsonValue);
    }
}
