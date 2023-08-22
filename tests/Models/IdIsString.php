<?php

declare(strict_types=1);

namespace Jenssegers\Mongodb\Tests\Models;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class IdIsString extends Eloquent
{
    protected $connection = 'mongodb';
    protected static $unguarded = true;
    protected $casts = [
        '_id' => 'string',
    ];
}
