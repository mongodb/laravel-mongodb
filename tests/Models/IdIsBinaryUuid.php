<?php

declare(strict_types=1);

namespace Jenssegers\Mongodb\Tests\Models;

use Jenssegers\Mongodb\Eloquent\Casts\BinaryUuid;
use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class IdIsBinaryUuid extends Eloquent
{
    protected $connection = 'mongodb';
    protected static $unguarded = true;
    protected $casts = [
        '_id' => BinaryUuid::class,
    ];
}
