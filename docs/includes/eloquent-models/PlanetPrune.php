<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Prunable;

class Planet extends Model
{
    use Prunable;

    public function prunable()
    {
        // matches models in which the solar_system field contains a null value
        return static::whereNull('solar_system');
    }

    protected function pruning()
    {
        // delete photo assets of this model
    }
}
