<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Cargo;

use App\Models\Movie;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\SQLiteBuilder;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Tests\TestCase;

use function assert;

class FindOneTest extends TestCase
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