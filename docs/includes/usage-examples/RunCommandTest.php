<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use MongoDB\Laravel\Tests\TestCase;

class RunCommandTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testRunCommand(): void
    {
        require_once __DIR__ . '/Movie.php';

        // begin-command
        $cursor = DB::connection('mongodb')
            ->command(['listCollections' => 1]);

        foreach ($cursor as $coll) {
            echo $coll['name'] . '<br>';
        }

        // end-command

        $this->assertNotNull($cursor);
    }
}
