<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use MongoDB\Driver\Cursor;
use MongoDB\Laravel\Tests\TestCase;

class RunCommandTest extends TestCase
{
    public function testRunCommand(): void
    {
        // begin-command
        $cursor = DB::connection('mongodb')
            ->command(['listCollections' => 1]);

        foreach ($cursor as $coll) {
            echo $coll['name'] . "<br>\n";
        }

        // end-command

        $this->assertNotNull($cursor);
        $this->assertInstanceOf(Cursor::class, $cursor);
        $this->expectOutputRegex('/<br>/');
    }
}
