<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\EmbedsMany;

class SpaceShip extends Model
{
  protected $connection = 'mongodb';

  public function cargo():EmbedsMany
  {
      return $this->embedsMany(Cargo::class);
  }

}
