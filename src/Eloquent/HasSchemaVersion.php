<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Eloquent;

trait HasSchemaVersion
{
    public $currentSchemaVersion = 1;

    /**
     * Auto call on model instance as booting
     *
     * @return void
     */
    public static function bootHasSchemaVersion(): void
    {
        static::saving(function ($model) {
          if (!$model->getSchemaVersion()) {
              $model->setSchemaVersion($model->currentSchemaVersion);
          }
        });

        static::retrieved(function ($model) {
            if ($model->getSchemaVersion() < $model->currentSchemaVersion) {
                $model->migrateSchema($model->getSchemaVersion());
            }
        });
    }

    /**
     * Migrate document to the current schema version.
     */
    public function migrateSchema(int $fromVersion): void
    {
    }

    /**
     * Get Current document version
     *
     * @return int
     */
    public function getSchemaVersion(): int
    {
        return $this->{static::getSchemaVersionKey()} ?? 0;
    }

    /**
     * @param int $version
     * @return void
     */
    public function setSchemaVersion(int $version): void
    {
            $this->{static::getSchemaVersionKey()} = $version;
    }

    /**
     * Get document version key
     *
     * @return string
     */
    public static function getSchemaVersionKey(): string
    {
       return 'schema_version';
    }
}
