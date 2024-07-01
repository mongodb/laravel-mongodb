<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use MongoDB\Laravel\Eloquent\HasDocumentVersion;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

/** @property int __v */
class DocumentVersion extends Eloquent
{
    use HasDocumentVersion;

    public $documentVersion = 1;

    protected $connection       = 'mongodb';
    protected $collection       = 'documentVersion';
    protected static $unguarded = true;
    public function migrateDocumentVersion(int $fromVersion): void
    {
        if ($fromVersion) {
            if ($fromVersion < 2) {
                $this->age = 35;
            }
        }
    }
}
