<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Eloquent;

use MongoDB\Laravel\Tests\Models\CollectedItem;
use MongoDB\Laravel\Tests\Models\CollectedItemCollection;
use MongoDB\Laravel\Tests\TestCase;

/** @see https://laravel.com/docs/eloquent-collections#custom-collections */
final class CollectedByTest extends TestCase
{
    protected function tearDown(): void
    {
        CollectedItem::truncate();

        parent::tearDown();
    }

    public function testNewCollection(): void
    {
        self::assertInstanceOf(CollectedItemCollection::class, (new CollectedItem())->newCollection());
    }

    public function testQueryResults(): void
    {
        CollectedItem::create(['name' => 'knife']);
        CollectedItem::create(['name' => 'fork']);

        $all = CollectedItem::all();
        self::assertInstanceOf(CollectedItemCollection::class, $all);
        self::assertCount(2, $all);

        $found = CollectedItem::where('name', 'knife')->get();
        self::assertInstanceOf(CollectedItemCollection::class, $found);
        self::assertCount(1, $found);
    }

    public function testHasMany(): void
    {
        $parent = CollectedItem::create(['name' => 'drawer']);
        $parent->children()->create(['name' => 'knife']);
        $parent->children()->create(['name' => 'fork']);

        $children = $parent->children;
        self::assertInstanceOf(CollectedItemCollection::class, $children);
        self::assertCount(2, $children);

        $eagerLoaded = CollectedItem::with('children')->find($parent->getKey())->children;
        self::assertInstanceOf(CollectedItemCollection::class, $eagerLoaded);
        self::assertCount(2, $eagerLoaded);
    }

    public function testEmbedsMany(): void
    {
        $parent = CollectedItem::create(['name' => 'drawer']);
        $parent->embeddedChildren()->create(['name' => 'knife']);
        $parent->embeddedChildren()->create(['name' => 'fork']);

        self::assertInstanceOf(CollectedItemCollection::class, $parent->embeddedChildren);

        $embedded = CollectedItem::find($parent->getKey())->embeddedChildren;
        self::assertInstanceOf(CollectedItemCollection::class, $embedded);
        self::assertCount(2, $embedded);
    }
}
