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
            $model->migrateSchema($model->upgradeSchemaVersion());
        });
    }

    /**
     * Migrate document to the current schema version.
     */
    public function migrateSchema(int $fromVersion): void
    {
    }

    /**
     * migrate schema and set current model version
     * @return void
     */
    public function upgradeSchemaVersion(): void
    {
        $version = $this->getSchemaVersion();

        if ($version && $version < $this->currentSchemaVersion) {
            $this->migrateSchema($version);
            $this->setSchemaVersion($this->currentSchemaVersion);
        }
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
