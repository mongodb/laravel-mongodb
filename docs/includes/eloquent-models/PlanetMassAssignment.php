<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Planet extends Model
{
      $fillable = ['name', 'gravitational_force', 'diameter', 'number_of_moons'];
}
