<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Movie;
use MongoDB\Laravel\Tests\TestCase;

class DistinctTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDistinct(): void
    {
        require_once __DIR__ . '/Movie.php';

        Movie::truncate();
        Movie::insert([
            [
                'title' => 'The Big House',
                'imdb' => [
                    'rating' => 7.3,
                    'votes' => 1161,
                ],
            ],
            [
                'title' => 'The Divorcee',
                'imdb' => [
                    'rating' => 6.9,
                    'votes' => 1740,
                ],
            ],
            [
                'title' => 'Morocco',
                'imdb' => [
                    'rating' => 7.3,
                    'votes' => 3566,
                ],
            ],
        ]);

        // begin-distinct
        $ratings = Movie::where('year', 1930)
            ->select('imdb.rating')
            ->distinct()
            ->get();

        echo $ratings;
        // end-distinct

        $this->expectOutputString('[[6.9],[7.3]]');
    }
}
