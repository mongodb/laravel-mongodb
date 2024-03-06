<?php

declare(strict_types=1);

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsToMany;

class SpaceExplorer extends Model
{
    protected $connection = 'mongodb';

    public function planetsVisited(): BelongsToMany
    {
        return $this->belongsToMany(Planet::class);
    }
}
