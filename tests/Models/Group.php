<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use MongoDB\Laravel\Eloquent\DocumentModel;

class Group extends Model
{
    use DocumentModel;

    protected $keyType = 'string';
    protected $connection = 'mongodb';
    protected $table = 'groups';
    protected static $unguarded = true;

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'users', 'groups', 'users', 'id', 'id', 'users');
    }
}
