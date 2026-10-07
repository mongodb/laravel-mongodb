<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use MongoDB\Laravel\Eloquent\DocumentModel;

class Director extends Model
{
    use DocumentModel;
    use SoftDeletes;

    protected $keyType = 'string';
    protected $connection = 'mongodb';
    protected $table = 'directors';
    protected static $unguarded = true;
    protected $casts = ['deleted_at' => 'datetime'];

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function films(): HasMany
    {
        return $this->hasMany(Film::class);
    }
}
