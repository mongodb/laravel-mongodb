<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Movie;
use MongoDB\Laravel\Tests\TestCase;

class FindOneTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testFindOne(): void
    {
        require_once __DIR__ . '/Movie.php';

        Movie::truncate();
        Movie::insert([
            ['title' => 'The Shawshank Redemption', 'directors' => ['Frank Darabont', 'Rob Reiner']],
        ]);

        // begin-find-one
        $movie = Movie::where('directors', 'Rob Reiner')
          ->orderBy('id')
          ->first();

        echo $movie->toJson();
        // end-find-one

        $this->assertInstanceOf(Movie::class, $movie);
        $this->expectOutputRegex('/^{"title":"The Shawshank Redemption","directors":\["Frank Darabont","Rob Reiner"\],"id":"[a-z0-9]{24}"}$/');
    }
}
