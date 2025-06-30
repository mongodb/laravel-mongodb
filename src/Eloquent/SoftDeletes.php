<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Eloquent;

use function sprintf;
use function trigger_error;

use const E_USER_DEPRECATED;

trigger_error(sprintf('Since mongodb/laravel-mongodb:5.5, trait "%s" is deprecated, use "%s" instead.', SoftDeletes::class, \Illuminate\Database\Eloquent\SoftDeletes::class), E_USER_DEPRECATED);

/** @deprecated since mongodb/laravel-mongodb:5.5, use \Illuminate\Database\Eloquent\SoftDeletes instead */
trait SoftDeletes
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    /** @inheritdoc */
    public function getQualifiedDeletedAtColumn()
    {
        return $this->getDeletedAtColumn();
    }
}
