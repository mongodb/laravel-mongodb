<?php

declare(strict_types=1);

namespace Jenssegers\Mongodb\Tests\Models;

use Jenssegers\Mongodb\Eloquent\Casts\ObjectId;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class CastObjectId extends Eloquent
{
    protected $connection = 'mongodb';
    protected static $unguarded = true;
    protected $casts = [
        'oid' => ObjectId::class,
    ];
}
