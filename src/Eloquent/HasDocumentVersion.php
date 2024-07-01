<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Eloquent;

trait HasDocumentVersion
{
    /**
     * Auto call on model instance as booting
     *
     * @return void
     */
    public static function bootHasDocumentVersion(): void
    {
        static::saving(function ($model) {
          $version = $model->getDocumentVersion();

          if (!$version) {
              $model->setDocumentVersion(static::getCurrentVersion());
          }
        });

        static::retrieved(function ($model) {
            $model->migrateDocumentVersion($model->getDocumentVersion());
        });
    }

    /**
     * migrate model document version schema
     *
     * @param int|null $fromVersion
     * @return void
     */
    public function migrateDocumentVersion($fromVersion): void
    {
    }

    /**
     * Get Current document version
     *
     * @return int|null
     */
    public function getDocumentVersion(): ?int
    {
        return $this->{static::getDocumentVersionKey()} ?? null;
    }

    /**
     * @param int $version
     * @return void
     */
    public function setDocumentVersion(int $version): void
    {
            $this->{static::getDocumentVersionKey()} = $version;
    }

    /**
     * Get document version key
     *
     * @return string
     */
    protected static function getDocumentVersionKey(): string
    {
        return defined(static::class.'::DOCUMENT_VERSION_KEY')
            ? (string) static::DOCUMENT_VERSION_KEY
            : 'schema_version';
    }

    /**
     * @return int
     */
    protected static function getCurrentVersion(): int
    {
        return defined(static::class.'::DOCUMENT_VERSION')
            ? (int) static::DOCUMENT_VERSION
            : 1;
    }
}
