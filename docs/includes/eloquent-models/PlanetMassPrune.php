<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\MassPrunable;
use MongoDB\Laravel\Eloquent\Model;

class Planet extends Model
{
    use MassPrunable;

    public function prunable()
    {
        // matches models in which the solar_system field contains a null value
        return static::where('gravitational_force', '>', 0.5);
    }
}
