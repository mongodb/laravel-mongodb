<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Eloquent;

/** @deprecated 6.0.0 in favor of \Illuminate\Database\Eloquent\SoftDeletes */
trait SoftDeletes
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    /** @inheritdoc */
    public function getQualifiedDeletedAtColumn()
    {
        return $this->getDeletedAtColumn();
    }
}
