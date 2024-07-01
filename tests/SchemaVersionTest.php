<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests;

use MongoDB\Laravel\Tests\Models\SchemaVersion;
use MongoDB\Laravel\Tests\Models\User;

class SchemaVersionTest extends TestCase
{
    public function tearDown(): void
    {
        SchemaVersion::truncate();
    }

    public function testWithBasicDocument()
    {
        $document = new SchemaVersion(['name' => 'Luc']);
        $this->assertEmpty($document->getSchemaVersion());
        $document->save();

        $this->assertEquals(1, $document->getSchemaVersion());
        $this->assertNull($document->age);

        $document->currentSchemaVersion = 2;
        $document->migrateSchema($document->getSchemaVersion());

        $this->assertEquals(35, $document->age);
        $this->assertEquals(2, $document->getSchemaVersion());

        // Test without migration
        $newDocument = new SchemaVersion(['name' => 'Vador']);
        $newDocument->setSchemaVersion(2);
        $newDocument->save();
        $newDocument->currentSchemaVersion = 2;
        $newDocument->migrateSchema($newDocument->getSchemaVersion());

        $this->assertEquals(2, $newDocument->getSchemaVersion());
        $this->assertNull($newDocument->age);

        $newDocument = SchemaVersion::query()->where('name', 'Vador')->first();
        $this->assertNull($newDocument->age);
    }
}
