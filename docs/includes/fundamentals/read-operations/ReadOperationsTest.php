<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Cargo;

// <add your imports here including any models and facades>

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\SQLiteBuilder;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Tests\TestCase;

use function assert;

class ReadOperationsTest extends TestCase
{

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testFindFilter(): void
    {
        require_once __DIR__ . '/Movie.php';

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
        require_once __DIR__ . '/Movie.php';

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
        require_once __DIR__ . '/Movie.php';

        // start-sort
        $movies = Movie::where('countries', 'Indonesia')
            ->orderBy('year')
            ->orderBy('title', 'desc')
            ->get();
        // end-sort

        $this->assertNotNull($movies);
        $this->assertCount(24, $movies);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testFirst(): void
    {
        require_once __DIR__ . '/Movie.php';

        // start-first
        $movies = Movie::where('runtime', 30)
            ->orderBy('_id')
            ->first();
        // end-first

        $this->assertNotNull($movies);
        $this->assertCount(1, $movies);
    }

}