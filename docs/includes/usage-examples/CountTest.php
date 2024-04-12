<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Movie;
use MongoDB\Laravel\Tests\TestCase;

class CountTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testCount(): void
    {
        require_once __DIR__ . '/Movie.php';

        Movie::truncate();
        Movie::insert([
            [
                'title' => 'Young Mr. Lincoln',
                'genres' => ['Biography', 'Drama'],
            ],
            [
                'title' => 'Million Dollar Mermaid',
                'genres' => ['Biography', 'Drama', 'Musical'],
            ],
        ]);

        // begin-count
        $biographies = Movie::where('genres', 'Biography')
            ->count();

        echo 'Number of documents: ' . $biographies;
        // end-count

        $this->assertEquals(2, $biographies);
        $this->expectOutputString('Number of documents: 2');
    }
}