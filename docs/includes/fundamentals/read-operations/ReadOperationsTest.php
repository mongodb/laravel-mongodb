<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Movie;
use Illuminate\Support\Facades\DB;
use MongoDB\Driver\ReadPreference;
use MongoDB\Laravel\Tests\TestCase;

use function json_encode;
use function ob_flush;

use const JSON_PRETTY_PRINT;

class ReadOperationsTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/Movie.php';

        parent::setUp();

        $moviesCollection = DB::connection('mongodb')->getCollection('movies');
        $moviesCollection->drop();
        $moviesCollection->createIndex(['plot' => 'text']);

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
            ['title' => 'movie_a', 'plot' => 'this is a love story'],
            ['title' => 'movie_b', 'plot' => 'love is a long story'],
            ['title' => 'movie_c', 'plot' => 'went on a trip'],
            ['title' => 'Carrie', 'year' => 1976],
            ['title' => 'Carrie', 'year' => 2002],
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

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testText(): void
    {
        // start-text
        $movies = Movie::where('$text', ['$search' => '"love story"'])
            ->get();
        // end-text

        $this->assertNotNull($movies);
        $this->assertCount(1, $movies);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testTextRelevance(): void
    {
        // start-text-relevance
        $movies = Movie::where('$text', ['$search' => '"love story"'])
            ->orderBy('score', ['$meta' => 'textScore'])
            ->get();
        // end-text-relevance

        $this->assertNotNull($movies);
        $this->assertCount(1, $movies);
        $this->assertEquals('this is a love story', $movies[0]->plot);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function exactArrayMatch(): void
    {
        // start-exact-array
        $movies = Movie::where('countries', ['Indonesia', 'Canada'])
            ->get();
        // end-exact-array

        $this->assertNotNull($movies);
        $this->assertCount(1, $movies);
        $this->assertEquals('Title 1', $movies[0]->title);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function arrayElemMatch(): void
    {
        // start-elem-match
        $movies = Movie::where('countries', 'in', ['Canada', 'Egypt'])
            ->get();
        // end-elem-match

        $this->assertNotNull($movies);
        $this->assertCount(2, $movies);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testReadPreference(): void
    {
        // start-read-pref
        $movies = Movie::where('title', 'Carrie')
            ->readPreference(ReadPreference::SECONDARY_PREFERRED)
            ->get();
        // end-read-pref

        $this->assertNotNull($movies);
        $this->assertCount(2, $movies);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testQueryLog(): void
    {
        // start-query-log
        DB::connection('mongodb')->enableQueryLog();

        Movie::where('title', 'Carrie')->get();
        Movie::where('year', '<', 2005)->get();
        Movie::where('imdb.rating', '>', 8.5)->get();

        $logs = DB::connection('mongodb')->getQueryLog();
        foreach ($logs as $log) {
            echo json_encode($log, JSON_PRETTY_PRINT);
        }

        // end-query-log

        $this->assertNotNull($logs);
        $this->expectOutputRegex('/^'
            . '\{'
            . '\s*"query"\s*:\s*"\{\\\"find\\\"\s*:\s*\\\"movies\\\",\s*\\\"filter\\\"\s*:\s*\{\\\"title\\\"\s*:\s*\\\"Carrie\\\"\}\}",'
            . '\s*"bindings"\s*:\s*\[\],'
            . '\s*"time"\s*:\s*\d+'
            . '\}'
            . '\{'
            . '\s*"query"\s*:\s*"\{\\\"find\\\"\s*:\s*\\\"movies\\\",\s*\\\"filter\\\"\s*:\s*\{\\\"year\\\"\s*:\s*\{\\\"\\$lt\\\"\s*:\s*\{\\\"\\$numberInt\\\"\s*:\s*\\\"2005\\\"\}\}\}\}",'
            . '\s*"bindings"\s*:\s*\[\],'
            . '\s*"time"\s*:\s*\d+'
            . '\}'
            . '\{'
            . '\s*"query"\s*:\s*"\{\\\"find\\\"\s*:\s*\\\"movies\\\",\s*\\\"filter\\\"\s*:\s*\{\\\"imdb.rating\\\"\s*:\s*\{\\\"\\$gt\\\"\s*:\s*\{\\\"\\$numberDouble\\\"\s*:\s*\\\"8\.5\\\"\}\}\}\}",'
            . '\s*"bindings"\s*:\s*\[\],'
            . '\s*"time"\s*:\s*\d+'
            . '\}'
            . '$/x');
    }
}
