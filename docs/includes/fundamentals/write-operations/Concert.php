<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Concert extends Model
{
    protected $connection = 'mongodb';
    protected $fillable = [ 'performer', 'venue', 'performanceDate' ];
    protected $casts = [ 'performanceDate' => 'datetime' ];
}
