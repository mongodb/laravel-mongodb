<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\HasSchemaVersion;
use MongoDB\Laravel\Eloquent\Model;

class Planet extends Model
{
    use HasSchemaVersion;

    public const SCHEMA_VERSION = 2;

    protected $fillable = ['name', 'type', 'galaxy'];

    /**
     * Migrate documents with a lower schema version to the most current
     * schema when inserting new data or retrieving from the database.
     */
    public function migrateSchema(int $fromVersion): void
    {
        if ($fromVersion < 2) {
            $this->galaxy = 'Milky Way';
        }
    }
}
