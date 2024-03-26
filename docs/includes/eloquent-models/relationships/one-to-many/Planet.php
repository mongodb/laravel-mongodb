<?php

declare(strict_types=1);

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\HasMany;

class Planet extends Model
{
    protected $connection = 'mongodb';

    public function moons(): HasMany
    {
        return $this->hasMany(Moon::class);
    }
}
