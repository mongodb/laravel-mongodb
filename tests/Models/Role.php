<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\DocumentModel;

class Role extends Model
{
    use DocumentModel;

    protected $keyType = 'string';
    protected $connection = 'mongodb';
    protected $table = 'roles';
    protected static $unguarded = true;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sqlUser(): BelongsTo
    {
        return $this->belongsTo(SqlUser::class);
    }
}
