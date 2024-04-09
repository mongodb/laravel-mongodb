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
        foreach (
            Movie::where('runtime', '>', 900)
            ->orderBy('_id')
            ->cursor() as $movie
        ) {
                echo $movie->toJson() . '<br>';
        }

        // end-find
        $this->expectOutputRegex('/^{"_id":"[a-z0-9]{24}","title":"Centennial","runtime":1256}\r\n{"_id":"[a-z0-9]{24}","title":"Baseball","runtime":1140}$/');
    }
}
