<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;
use MongoDB\Laravel\Relations\EmbedsMany;

class Casting extends Eloquent
{
    protected $connection       = 'mongodb';
    protected $collection = 'casting';

    protected $fillable = [
        'number'
    ];

    protected $casts = [
        'number' => 'int'
    ];
}
