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

    public function testCreateWithNullId()
    {
        $document = new DocumentVersion(['name' => 'Luc']);
        $this->assertEmpty($document->getDocumentVersion());
        $document->save();

        $this->assertEquals(1, $document->getDocumentVersion());
        $this->assertNull($document->age);

        $document = DocumentVersion::query()->where('name', 'Luc')->first();
        $this->assertEquals(35, $document->age);
        $this->assertEquals(2, $document->getDocumentVersion());

        // Test without migration
        $newDocument = new DocumentVersion(['name' => 'Vador']);
        $newDocument->setDocumentVersion(2);
        $newDocument->save();

        $this->assertEquals(2, $newDocument->getDocumentVersion());
        $this->assertNull($newDocument->age);

        $newDocument = DocumentVersion::query()->where('name', 'Vador')->first();
        $this->assertNull($newDocument->age);
    }
}
