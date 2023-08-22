<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use MongoDB\Laravel\Eloquent\Casts\ObjectId;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class CastObjectId extends Eloquent
{
    protected $connection = 'mongodb';
    protected static $unguarded = true;
    protected $casts = [
        'oid' => ObjectId::class,
    ];
}
