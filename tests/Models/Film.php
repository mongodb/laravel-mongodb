<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\DocumentModel;

class Film extends Model
{
    use DocumentModel;

    protected $keyType = 'string';
    protected $connection = 'mongodb';
    protected $table = 'films';
    protected static $unguarded = true;
    protected $casts = ['budget' => 'int'];

    public function director(): BelongsTo
    {
        return $this->belongsTo(Director::class);
    }
}
