<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use MongoDB\Laravel\Eloquent\HasSchemaVersion;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class SchemaVersion extends Eloquent
{
    use HasSchemaVersion;

    public const SCHEMA_VERSION = 2;

    protected $connection       = 'mongodb';
    protected $collection       = 'documentVersion';
    protected static $unguarded = true;

    public function migrateSchema(int $fromVersion): void
    {
        if ($fromVersion < 2) {
            $this->age = 35;
        }
    }
}
