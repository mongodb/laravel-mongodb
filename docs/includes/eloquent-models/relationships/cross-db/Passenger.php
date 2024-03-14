<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Eloquent\Model;

class Passenger extends Model
{
    protected $connection = 'mongodb';

    public function spaceship(): BelongsTo
    {
        return $this->belongsTo(SpaceShip::class);
    }
}
