<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Casts;

use Illuminate\Support\Collection;
use MongoDB\Laravel\Tests\Models\Casting;
use MongoDB\Laravel\Tests\TestCase;

use function collect;

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

        self::assertInstanceOf(Collection::class, $model->collectionValue);
        self::assertIsString($model->getRawOriginal('collectionValue'));
        self::assertEquals(collect(['g' => 'G-Eazy']), $model->collectionValue);

        $model->update(['collectionValue' => ['Dont let me go' => 'Even the longest of nights turn days']]);

        self::assertInstanceOf(Collection::class, $model->collectionValue);
        self::assertIsString($model->getRawOriginal('collectionValue'));
        self::assertEquals(collect(['Dont let me go' => 'Even the longest of nights turn days']), $model->collectionValue);

        $model->update(['collectionValue' => [['Dont let me go' => 'Even the longest of nights turn days']]]);

        self::assertInstanceOf(Collection::class, $model->collectionValue);
        self::assertIsString($model->getRawOriginal('collectionValue'));
        self::assertEquals(collect([['Dont let me go' => 'Even the longest of nights turn days']]), $model->collectionValue);
    }
}
