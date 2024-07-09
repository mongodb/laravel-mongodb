<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Eloquent;

use Error;
use LogicException;

use function sprintf;

/**
 * Use this trait to implement schema versioning in your models. The document
 * is updated automatically when its schema version retrieved from the database
 * is lower than the current schema version of the model.
 *
 *     class MyVersionedModel extends Model
 *     {
 *         use HasSchemaVersion;
 *
 *         public const int SCHEMA_VERSION = 1;
 *
 *         public function migrateSchema(int $fromVersion): void
 *         {
 *             // Your logic to update the document to the current schema version
 *         }
 *     }
 *
 * @see https://www.mongodb.com/docs/manual/tutorial/model-data-for-schema-versioning/
 *
 * Requires PHP 8.2+
 */
trait HasSchemaVersion
{
    /**
     * This method should be implemented in the model to migrate a document from
     * an older schema version to the current schema version.
     */
    public function migrateSchema(int $fromVersion): void
    {
    }

    public static function bootHasSchemaVersion(): void
    {
        static::saving(function ($model) {
            if ($model->getAttribute($model::getSchemaVersionKey()) === null) {
                $model->setAttribute($model::getSchemaVersionKey(), $model->getModelSchemaVersion());
            }
        });

        static::retrieved(function (self $model) {
            $version = $model->getSchemaVersion();

            if ($version < $model->getModelSchemaVersion()) {
                $model->migrateSchema($version);
                $model->setAttribute($model::getSchemaVersionKey(), $model->getModelSchemaVersion());
            }
        });
    }

    /**
     * Get Current document version, fallback to 0 if not set
     */
    public function getSchemaVersion(): int
    {
        return $this->{static::getSchemaVersionKey()} ?? 0;
    }

    protected static function getSchemaVersionKey(): string
    {
        return 'schema_version';
    }

    protected function getModelSchemaVersion(): int
    {
        try {
            return $this::SCHEMA_VERSION;
        } catch (Error) {
            throw new LogicException(sprintf('Constant %s::SCHEMA_VERSION is required when using HasSchemaVersion', $this::class));
        }
    }
}
