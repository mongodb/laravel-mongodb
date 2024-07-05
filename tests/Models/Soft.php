<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Builder;
use MongoDB\Laravel\Eloquent\DocumentModel;
use MongoDB\Laravel\Eloquent\MassPrunable;
use MongoDB\Laravel\Eloquent\SoftDeletes;

/** @property Carbon $deleted_at */
class Soft extends Model
{
    use DocumentModel;
    use SoftDeletes;
    use MassPrunable;

    protected $primaryKey = '_id';
    protected $keyType = 'string';
    protected $connection = 'mongodb';
    protected string $collection = 'soft';
    protected static $unguarded = true;
    protected $casts = ['deleted_at' => 'datetime'];

    public function prunable(): Builder
    {
        return $this->newQuery();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
