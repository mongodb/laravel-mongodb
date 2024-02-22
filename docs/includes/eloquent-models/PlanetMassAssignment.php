<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Planet extends Model
{
    /**
     * The mass assignable attributes.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'gravitational_force',
        'diameter',
        'moons',
    ];
}
