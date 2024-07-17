<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\HasSchemaVersion;
use MongoDB\Laravel\Eloquent\Model;

class Planet extends Model
{
    use HasSchemaVersion;

    public const SCHEMA_VERSION = 2;

    public function migrateSchema(int $fromVersion): void
    {
        if ($fromVersion < 2) {
            $this->galaxy = 'Milky Way';
        }
    }
}
