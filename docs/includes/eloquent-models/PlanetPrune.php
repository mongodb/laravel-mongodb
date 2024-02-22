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
        // Cleanup cations such as deleting photo assets of this
        // model or printing the Planet 'name' attribute to a log file
    }
}
