<?php

declare(strict_types=1);

namespace Jenssegers\Mongodb\Tests\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Group extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'groups';
    protected static $unguarded = true;

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
