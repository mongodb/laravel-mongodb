<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Eloquent;

use MongoDB\Laravel\Tests\Models\Book;
use MongoDB\Laravel\Tests\Models\User;
use MongoDB\Laravel\Tests\TestCase;

class GetOrdersTest extends TestCase
{
    public function testGetOrdersWithoutOrder(): void
    {
        $this->assertSame([], User::query()->getOrders());
    }

    public function testGetOrdersInOrderOfPrecedence(): void
    {
        $orders = User::query()
            ->orderBy('name')
            ->orderBy('age', 'desc')
            ->getOrders();

        $this->assertSame([
            ['column' => 'name', 'direction' => 'asc'],
            ['column' => 'age', 'direction' => 'desc'],
        ], $orders);
    }

    public function testGetOrdersReportsThePrimaryKeySort(): void
    {
        $orders = User::query()->orderBy('id', 'desc')->getOrders();

        $this->assertSame([['column' => 'id', 'direction' => 'desc']], $orders);
    }

    public function testGetOrdersNormalizesThePrimaryKeyAlias(): void
    {
        $orders = User::query()->orderBy('_id', 'desc')->getOrders();

        $this->assertSame([['column' => 'id', 'direction' => 'desc']], $orders);
    }

    public function testGetOrdersKeepsTheUnderscoreIdForACustomKey(): void
    {
        $orders = Book::query()->orderBy('_id', 'desc')->getOrders();

        $this->assertSame([['column' => '_id', 'direction' => 'desc']], $orders);
    }

    public function testGetOrdersReportsATextScoreSortAsDescending(): void
    {
        $orders = User::query()->orderBy('score', ['$meta' => 'textScore'])->getOrders();

        $this->assertSame([['column' => 'score', 'direction' => 'desc']], $orders);
    }

    public function testGetOrdersReportsANaturalSort(): void
    {
        $orders = User::query()->orderBy('natural', 'desc')->getOrders();

        $this->assertSame([['column' => '$natural', 'direction' => 'desc']], $orders);
    }

    public function testGetOrdersIsClearedByReorder(): void
    {
        $query = User::query()->orderBy('name');
        $query->reorder();

        $this->assertSame([], $query->getOrders());
    }
}
