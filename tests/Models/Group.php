<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Group extends Eloquent
{
    protected $connection       = 'mongodb';
    protected $collection       = 'groups';
    protected static $unguarded = true;

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'users', 'groups', 'users', '_id', '_id', 'users');
    }
}
