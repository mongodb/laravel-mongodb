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
        Movie::insert([
            [
                'title' => 'Centennial',
                'runtime' => 1256,
            ],
            [
                'title' => 'Baseball',
                'runtime' => 1140,
            ],
        ]);

        // begin-find
        $movies = Movie::where('runtime', '>', 900)
            ->orderBy('_id')
            ->get();
        // end-find

        $this->assertEquals(2, $movies->count());
    }
}
