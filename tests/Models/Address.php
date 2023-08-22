<?php

declare(strict_types=1);

namespace Jenssegers\Mongodb\Tests\Models;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;
use Jenssegers\Mongodb\Relations\EmbedsMany;

class Address extends Eloquent
{
    protected $connection = 'mongodb';
    protected static $unguarded = true;

    public function addresses(): EmbedsMany
    {
        return $this->embedsMany(self::class);
    }
}
