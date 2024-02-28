<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\HasOne;

class Planet extends Model
{
  protected $connection = 'mongodb';

  public function orbit(): HasOne
  {
      return $this->hasOne(Orbit::class);
  }

}
