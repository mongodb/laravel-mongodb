<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Movie;
use MongoDB\Laravel\Tests\TestCase;

class InsertManyTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testInsertMany(): void
    {
        require_once __DIR__ . '/Movie.php';

        Movie::truncate();

        // begin-insert-many
        $result = Movie::insert([
            [
                'title' => 'Anatomy of a Fall',
                'year' => 2023,
            ],
            [
                'title' => 'The Boy and the Heron',
                'year' => 2023,
            ],
            [
                'title' => 'Passages',
                'year' => 2023,
            ],
        ]);

        echo 'Insert operation success: ' . $result;
        // end-insert-many

        //$this->assertInstanceOf(Movie::class, $movie);
        //$this->expectOutputRegex('/^{"title":"Marriage Story","year":2019,"runtime":136,"updated_at":".{27}","created_at":".{27}","_id":"[a-z0-9]{24}"}$/');
    }
}