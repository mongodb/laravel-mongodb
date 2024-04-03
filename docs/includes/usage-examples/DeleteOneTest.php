<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Movie;
use MongoDB\Laravel\Tests\TestCase;

class DeleteOneTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testDeleteOne(): void
    {
        require_once __DIR__ . '/Movie.php';

        Movie::truncate();
        Movie::insert([
            [
                'title' => 'Capote',
                'runtime' => 114
            ],
        ]);

        // begin-delete-one
        $deleted = Movie::where('title', 'Capote')
            ->orderBy('_id')
            ->first()
            ->delete();

        echo 'Deleted documents: ' . $deleted;
        // end-delete-one

        $this->assertTrue($deleted);
        $this->expectOutputString('Deleted documents: 1');
    }
}
