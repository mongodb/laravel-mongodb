<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;

class Orbit extends Model
{
  protected $connection = 'mongodb';

  public function planet(): BelongsTo
  {
      return $this->belongsTo(Planet::class);
  }

}
