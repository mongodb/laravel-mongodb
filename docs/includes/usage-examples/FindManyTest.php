<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Movie;
use MongoDB\Laravel\Tests\TestCase;

class FindManyTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testFindMany(): void
    {
        require_once __DIR__ . '/Movie.php';

        Movie::truncate();

        // begin-find
        $movies = Movie::where('runtime', '>', 800)
            ->orderBy('_id')
            ->get();

        foreach ($movies as $m) {
            echo $m->toJson() . '<br>';
        }

        // end-find

        $this->assertInstanceOf(Movie::class, $movie);
    }
}
