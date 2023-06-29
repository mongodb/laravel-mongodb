<?php

declare(strict_types=1);

namespace Jenssegers\Mongodb\Tests\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jenssegers\Mongodb\Eloquent\Builder;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

/**
 * Class Item.
 *
 * @property \Carbon\Carbon $created_at
 */
class Item extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'items';
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
