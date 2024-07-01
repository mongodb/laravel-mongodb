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
            $versionKey = static::getDocumentVersionKey();
            if (!empty($versionKey)) {
                $model->{$versionKey} = defined(static::class.'::DOCUMENT_VERSION')
                    ? (int) static::DOCUMENT_VERSION
                    : 1;
            }
        });
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
            : '__v';
    }
}
