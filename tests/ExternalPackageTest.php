<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests;

use Illuminate\Http\Request;
use MongoDB\Laravel\Tests\Models\User;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Test integration with external packages.
 */
class ExternalPackageTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        User::truncate();
    }

    /**
     * Integration test with spatie/laravel-query-builder.
     */
    public function testSpacieQueryBuilder(): void
    {
        User::insert([
            ['name' => 'Jimmy Doe', 'birthday' => '2012-11-12', 'role' => 'user'],
            ['name' => 'John Doe', 'birthday' => '1980-07-08', 'role' => 'admin'],
            ['name' => 'Jane Doe', 'birthday' => '1983-09-10', 'role' => 'admin'],
            ['name' => 'Jess Doe', 'birthday' => '2014-05-06', 'role' => 'user'],
        ]);

        $request = Request::create('/users', 'GET', ['filter' => ['role' => 'admin'], 'sort' => '-birthday']);
        $result = QueryBuilder::for(User::class, $request)
            ->allowedFilters([
                AllowedFilter::exact('role'),
            ])
            ->allowedSorts([
                AllowedSort::field('birthday'),
            ])
            ->get();

        $this->assertCount(2, $result);
        $this->assertSame('Jane Doe', $result[0]->name);
    }
}
