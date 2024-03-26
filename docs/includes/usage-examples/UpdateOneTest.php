<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Movie;
use MongoDB\Laravel\Tests\TestCase;

class UpdateOneTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function updateOne(): void
    {
        require 'Models/Movie.php';

      // begin-update-one
        $updates = Movie::where('title', 'Carol')
          ->orderBy('_id')
          ->first()
          ->update([
              'imdb' => [
                  'rating' => 7.3,
                  'votes' => 142000,
              ],
          ]);

        echo 'Updated documents: ' . $updates;
      // end-update-one

      // <optionally, add assertions>
    }

    // ... <add additional test cases here>
}
