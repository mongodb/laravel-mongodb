<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Eloquent;

trait HasDocumentVersion
{
    public $documentVersion = 1;

    /**
     * Auto call on model instance as booting
     *
     * @return void
     */
    public static function bootHasDocumentVersion(): void
    {
        static::saving(function ($model) {
          $version = $model->getDocumentVersion();
          if (empty($version)) {
              $model->{static::getDocumentVersionKey()} = $model->documentVersion ?? 1;
          }
        });

        static::retrieved(function ($model) {
            $model->migrateDocumentVersion($model->getDocumentVersion() ?? 0);
        });
    }

    /**
     * migrate model document version schema
     *
     * @param int $fromVersion
     * @return void
     */
    public function migrateDocumentVersion(int $fromVersion): void {}

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
}
