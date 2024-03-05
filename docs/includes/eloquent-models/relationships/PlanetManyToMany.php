<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsToMany;

class Planet extends Model
{
  protected $connection = 'mongodb';

  public function visitors(): BelongsToMany
  {
      return $this->belongsToMany(SpaceExplorer::class);
  }

}
