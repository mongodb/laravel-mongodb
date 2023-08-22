<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;
use MongoDB\Laravel\Relations\EmbedsMany;

class Address extends Eloquent
{
    protected $connection = 'mongodb';
    protected static $unguarded = true;

    public function addresses(): EmbedsMany
    {
        return $this->embedsMany(self::class);
    }
}
