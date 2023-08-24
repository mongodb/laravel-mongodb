<?php

declare(strict_types=1);

namespace Jenssegers\Mongodb\Tests\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Client extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'clients';
    protected static $unguarded = true;

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function photo(): MorphOne
    {
        return $this->morphOne(Photo::class, 'has_image');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class, 'data.client_id', 'data.client_id');
    }
}
