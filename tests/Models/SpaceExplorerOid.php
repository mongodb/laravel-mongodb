<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;

class SpaceExplorerOid extends Eloquent
{
    protected $connection = 'mongodb';
    protected static $unguarded = true;

    public function getIdAttribute($value = null)
    {
        return $value;
    }

    public function planetsVisited()
    {
        return $this->belongsToMany(PlanetOid::class);
    }
}
