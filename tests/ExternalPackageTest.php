<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests;

use Composer\InstalledVersions;
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

    public function testSpacieQueryBuilder(): void
    {
        if (! InstalledVersions::isInstalled('spatie/laravel-query-builder')) {
            $this->markTestSkipped('spatie/laravel-query-builder is not installed.');
        }

        User::insert([
            ['name' => 'Jane Doe', 'birthday' => '1983-09-10', 'role' => 'admin'],
            ['name' => 'John Doe', 'birthday' => '1980-07-08', 'role' => 'admin'],
            ['name' => 'Jimmy Doe', 'birthday' => '2012-11-12', 'role' => 'user'],
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
