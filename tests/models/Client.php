<?php

declare(strict_types=1);

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
        return $this->belongsToMany('User');
    }

    public function photo(): MorphOne
    {
        return $this->morphOne('Photo', 'imageable');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany('Address', 'data.client_id', 'data.client_id');
    }
}
