<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Database\Relations\BelongsTo;

class Passenger extends Model
{
  protected $connection = 'mongodb';

  public function spaceship(): BelongsTo
  {
    return $this->BelongsTo(SpaceShip::class);
  }

}
