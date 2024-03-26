<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Movie;
use MongoDB\Laravel\Tests\TestCase;

use function print_r;

class UpdateOneTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function updateOne(): void
    {
        require_once __DIR__ . '/Movie.php';

      // begin-find-one
        $movie = Movie::where('directors', 'Rob Reiner')
          ->orderBy('_id')
          ->first();

        print_r($movie->toJson());
      // end-find-one

      // <optionally, add assertions>
    }

    // ... <add additional test cases here>
}
