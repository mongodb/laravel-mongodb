<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use MongoDB\Laravel\Eloquent\HasSchemaVersion;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class SchemaVersion extends Eloquent
{
    use HasSchemaVersion;

    protected $connection       = 'mongodb';
    protected $collection       = 'documentVersion';
    protected static $unguarded = true;

    public function migrateSchema($fromVersion): void
    {
        if ($fromVersion) {
            if ($fromVersion < 2) {
                $this->age = 35;

                $this->setSchemaVersion(2);
            }
        }
    }
}
