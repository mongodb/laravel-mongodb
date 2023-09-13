<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Role extends Eloquent
{
    protected $connection       = 'mongodb';
    protected $collection       = 'roles';
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
