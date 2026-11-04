<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use MongoDB\Laravel\Eloquent\Casts\AsBinaryUuid;
use MongoDB\Laravel\Eloquent\Model;

class CastAsBinaryUuid extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'cast_as_binary_uuids';
    protected static $unguarded = true;
    protected $casts            = [
        'uuid' => AsBinaryUuid::class,
    ];
}
