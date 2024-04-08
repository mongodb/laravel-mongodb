<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Movie;
use MongoDB\Laravel\Tests\TestCase;

class UpdateManyTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testUpdateMany(): void
    {
        require_once __DIR__ . '/Movie.php';

        Movie::truncate();

        // begin-update-many
        $updates = Movie::where('imdb.rating', '>', 9.0)
            ->update(['$set' => ['acclaimed' => true]]);

        echo 'Updated documents: ' . $updates;
        // end-update-many

        $this->assertTrue($updates);
        $this->expectOutputString('Updated documents: 20');
    }
}
