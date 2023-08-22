<?php

declare(strict_types=1);

namespace Jenssegers\Mongodb\Tests\Models;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class IdIsInt extends Eloquent
{
    protected $keyType = 'int';
    protected $connection = 'mongodb';
    protected static $unguarded = true;
    protected $casts = [
        '_id' => 'int',
    ];
}
