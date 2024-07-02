<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;

class PlanetOid extends Eloquent
{
    protected $connection = 'mongodb';
    protected static $unguarded = true;

    public function getIdAttribute($value = null)
    {
        return $value;
    }

    public function visitors()
    {
        return $this->belongsToMany(SpaceExplorerOid::class);
    }
}
