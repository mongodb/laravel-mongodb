<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MongoDB\Laravel\Eloquent\HybridRelations;

class SpaceShip extends Model
{
  protected $connection = 'mysql';

  public $primaryKey = 'id';
  public function passengers(): HasMany
  {
      return $this->hasMany(Passenger::class);
  }

}
