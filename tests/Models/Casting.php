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
        'intNumber',
        'floatNumber',
        'decimalNumber',
        'stringContent',
        'stringContent',
        'booleanValue',
        'objectValue',
        'jsonValue',
    ];

    protected $casts = [
        'intNumber' => 'int',
        'floatNumber' => 'float',
        'decimalNumber' => 'decimal:2',
        'stringContent' => 'string',
        'booleanValue' => 'boolean',
        'objectValue' => 'object',
        'jsonValue' => 'json',
    ];
}
