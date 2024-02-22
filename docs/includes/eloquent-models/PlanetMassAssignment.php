<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Planet extends Model
{
    /**
     * The attributes that are mass assignable.
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
