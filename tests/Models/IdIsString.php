<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;

class IdIsString extends Eloquent
{
    protected $connection = 'mongodb';
    protected static $unguarded = true;
    protected $casts = [
        '_id' => 'string',
    ];
}
