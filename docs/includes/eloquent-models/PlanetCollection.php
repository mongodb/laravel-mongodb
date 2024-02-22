<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Planet extends Model
{
    protected $collection = 'celestial_body';
}
