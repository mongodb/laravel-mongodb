<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use MongoDB\Laravel\Eloquent\Casts\AsObjectId;
use MongoDB\Laravel\Eloquent\Model;

class CastAsObjectId extends Model
{
    protected $connection = 'mongodb';
    protected static $unguarded = true;
    protected $casts            = [
        'oid' => AsObjectId::class,
    ];
}
