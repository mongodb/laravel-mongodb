<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Movie;
use MongoDB\Laravel\Tests\TestCase;

class InsertOneTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testInsertOne(): void
    {
        require_once __DIR__ . '/Movie.php';

        Movie::truncate();

        // begin-insert-one
        $movie = Movie::create([
            'title' => 'Marriage Story',
            'year' => 2019,
            'runtime' => 136,
        ]);

        echo $movie->toJson();
        // end-insert-one

        $this->assertInstanceOf(Movie::class, $movie);
        $this->expectOutputString('{"title":"Marriage Story","year":2019,"runtime":136,"updated_at":"2024-04-09T15:57:44.370000Z","created_at":"2024-04-09T15:57:44.370000Z","_id":"66156578196dd197720a5282"}');
    }
}
