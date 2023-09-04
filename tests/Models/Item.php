<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Builder;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

/** @property Carbon $created_at */
class Item extends Eloquent
{
    protected $connection       = 'mongodb';
    protected $collection       = 'items';
    protected static $unguarded = true;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeSharp(Builder $query)
    {
        return $query->where('type', 'sharp');
    }
}
