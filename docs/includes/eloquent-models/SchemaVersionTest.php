<?php

declare(strict_types=1);

namespace App\Tests;

use App\Models\Planet;
use MongoDB\Laravel\Tests\TestCase;

class SchemaVersionTest extends TestCase
{
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testSchemaVersion(): void
    {
        require_once __DIR__ . '/PlanetSchemaVersion.php';

        Planet::truncate();

        $saturn = Planet::create([
            'name' => 'Saturn',
            'type' => 'gas',
        ]);
  
        $wasp = Planet::create([
            'name' => 'WASP-39 b',
            'type' => 'gas',
            'schema_version' => 1,
        ]);

        $planets = Planet::where('type', 'gas')
            ->get();
        echo 'After migration:\n' . $planets[0];

        $this->assertCount(2, $planets);
        $this->assertEquals(2, $planets[0]->schema_version);
    }
}
