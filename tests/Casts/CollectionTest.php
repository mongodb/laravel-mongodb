<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Casts;

use Illuminate\Support\Collection;
use MongoDB\Laravel\Tests\Models\Casting;
use MongoDB\Laravel\Tests\TestCase;

/** @group hans */
class CollectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Casting::truncate();
    }

    public function testCollection(): void
    {
        $model = Casting::query()->create(['collectionValue' => ['g' => 'G-Eazy']]);
        $check = Casting::query()->find($model->_id);

        self::assertInstanceOf(Collection::class,$check->collectionValue);
        self::assertEquals(collect(['g' => 'G-Eazy']),$check->collectionValue);

        $model->update(['collectionValue' => ['Dont let me go' => 'Even the longest of nights turn days']]);
        $check = Casting::query()->find($model->_id);

        self::assertInstanceOf(Collection::class,$check->collectionValue);
        self::assertEquals(collect(['Dont let me go' => 'Even the longest of nights turn days']),$check->collectionValue);
    }
}
