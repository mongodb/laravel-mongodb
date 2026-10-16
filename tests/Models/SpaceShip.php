<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\DocumentModel;
use MongoDB\Laravel\Relations\EmbedsMany;
use MongoDB\Laravel\Relations\EmbedsOne;

class SpaceShip extends Model
{
    use DocumentModel;

    protected $keyType = 'string';
    protected $connection = 'mongodb';
    protected static $unguarded = true;
    protected $with = ['cargo', 'pilot'];

    public function cargo(): EmbedsMany
    {
        return $this->embedsMany(Cargo::class);
    }

    public function pilot(): EmbedsOne
    {
        return $this->embedsOne(Pilot::class);
    }
}
