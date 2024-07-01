<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests;

use MongoDB\Laravel\Tests\Models\DocumentVersion;
use MongoDB\Laravel\Tests\Models\User;

class DocumentVersionTest extends TestCase
{
    public function tearDown(): void
    {
        DocumentVersion::truncate();
    }

    public function testWithBasicDocument()
    {
        $document = new DocumentVersion(['name' => 'Luc']);
        $this->assertEmpty($document->getSchemaVersion());
        $document->save();

        $this->assertEquals(1, $document->getSchemaVersion());
        $this->assertNull($document->age);

        $document->currentSchemaVersion = 2;
        $document->migrateSchemaVersion($document->getSchemaVersion());

        $this->assertEquals(35, $document->age);
        $this->assertEquals(2, $document->getSchemaVersion());

        // Test without migration
        $newDocument = new DocumentVersion(['name' => 'Vador']);
        $newDocument->setSchemaVersion(2);
        $newDocument->save();
        $newDocument->currentSchemaVersion = 2;
        $newDocument->migrateSchema($newDocument->getSchemaVersion());

        $this->assertEquals(2, $newDocument->getSchemaVersion());
        $this->assertNull($newDocument->age);

        $newDocument = DocumentVersion::query()->where('name', 'Vador')->first();
        $this->assertNull($newDocument->age);
    }
}
