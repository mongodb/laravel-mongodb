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
            $versionKey = static::getDocumentVersionKey();
            if (!empty($versionKey)) {
                $model->{$versionKey} = $model->documentVersion ?? 1;
            }
        });

        static::retrieved(function ($model) {
            $model->migrateDocumentVersion($model->getDocumentVersion());
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
     * @return int
     */
    public function getDocumentVersion(): int
    {
        return (int) $this->{static::getDocumentVersionKey()} ?? 0;
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
