<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Movie;
use MongoDB\Laravel\Tests\TestCase;

class ReadOperationsTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/Movie.php';

        parent::setUp();

        Movie::truncate();
        Movie::insert([
            ['year' => 2010, 'imdb' => ['rating' => 9]],
            ['year' => 2010, 'imdb' => ['rating' => 9.5]],
            ['year' => 2010, 'imdb' => ['rating' => 7]],
            ['year' => 1999, 'countries' => ['Indonesia', 'Canada'], 'title' => 'Title 1'],
            ['year' => 1999, 'countries' => ['Indonesia'], 'title' => 'Title 2'],
            ['year' => 1999, 'countries' => ['Indonesia'], 'title' => 'Title 3'],
            ['year' => 1999, 'countries' => ['Indonesia'], 'title' => 'Title 4'],
            ['year' => 1999, 'countries' => ['Canada'], 'title' => 'Title 5'],
            ['year' => 1999, 'runtime' => 30],
        ]);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testFindFilter(): void
    {
        // start-query
        $movies = Movie::where('year', 2010)
            ->where('imdb.rating', '>', 8.5)
            ->get();
        // end-query

        $this->assertNotNull($movies);
        $this->assertCount(2, $movies);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSkipLimit(): void
    {
        // start-skip-limit
        $movies = Movie::where('year', 1999)
            ->skip(2)
            ->take(3)
            ->get();
        // end-skip-limit

        $this->assertNotNull($movies);
        $this->assertCount(3, $movies);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSort(): void
    {
        // start-sort
        $movies = Movie::where('countries', 'Indonesia')
            ->orderBy('year')
            ->orderBy('title', 'desc')
            ->get();
        // end-sort

        $this->assertNotNull($movies);
        $this->assertCount(4, $movies);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testFirst(): void
    {
        // start-first
        $movie = Movie::where('runtime', 30)
            ->orderBy('_id')
            ->first();
        // end-first

        $this->assertNotNull($movie);
        $this->assertInstanceOf(Movie::class, $movie);
    }
}
