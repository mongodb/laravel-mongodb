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

        $this->assertTrue($result);
        $this->expectOutputString('Insert operation success: 1');
    }
}