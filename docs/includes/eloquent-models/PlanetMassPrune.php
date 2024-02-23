<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Eloquent\MassPrunable;

class Planet extends Model
{
    use MassPrunable;

    public function prunable()
    {
        // matches models in which the gravitational_force field contains
        // a value greater than 0.5
        return static::where('gravitational_force', '>', 0.5);
    }
}
