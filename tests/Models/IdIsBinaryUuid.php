<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use MongoDB\Laravel\Eloquent\Casts\BinaryUuid;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class IdIsBinaryUuid extends Eloquent
{
    protected $connection       = 'mongodb';
    protected static $unguarded = true;
    protected $casts            = [
        '_id' => BinaryUuid::class,
    ];
}
