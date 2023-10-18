<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Carbon\Carbon;
use MongoDB\Laravel\Eloquent\Builder;
use MongoDB\Laravel\Eloquent\MassPrunable;
use MongoDB\Laravel\Eloquent\Model as Eloquent;
use MongoDB\Laravel\Eloquent\SoftDeletes;

/** @property Carbon $deleted_at */
class Soft extends Eloquent
{
    use SoftDeletes;
    use MassPrunable;

    protected $connection       = 'mongodb';
    protected $collection       = 'soft';
    protected static $unguarded = true;
    protected $casts            = ['deleted_at' => 'datetime'];

    public function prunable(): Builder
    {
        return $this->newQuery();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
