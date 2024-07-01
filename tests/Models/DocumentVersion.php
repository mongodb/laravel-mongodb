<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use MongoDB\Laravel\Eloquent\HasDocumentVersion;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class DocumentVersion extends Eloquent
{
    use HasDocumentVersion;

    protected $connection       = 'mongodb';
    protected $collection       = 'documentVersion';
    protected static $unguarded = true;

    public function migrateDocumentVersion($fromVersion): void
    {
        if ($fromVersion) {
            if ($fromVersion < 2) {
                $this->age = 35;

                $this->setDocumentVersion(2);
            }
        }
    }
}
