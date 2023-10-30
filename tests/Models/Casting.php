<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use MongoDB\Laravel\Eloquent\Casts\BinaryUuid;
use MongoDB\Laravel\Eloquent\Casts\ObjectId;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Casting extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'casting';

    protected $fillable = [
        'uuid',
        'oid',
        'intNumber',
        'floatNumber',
        'decimalNumber',
        'stringContent',
        'stringContent',
        'booleanValue',
        'objectValue',
        'jsonValue',
        'collectionValue',
        'dateField',
        'datetimeField',
    ];

    protected $casts = [
        'uuid' => BinaryUuid::class,
        'oid' => ObjectId::class,
        'intNumber' => 'int',
        'floatNumber' => 'float',
        'decimalNumber' => 'decimal:2',
        'stringContent' => 'string',
        'booleanValue' => 'boolean',
        'objectValue' => 'object',
        'jsonValue' => 'json',
        'collectionValue' => 'collection',
        'dateField' => 'date',
        'datetimeField' => 'datetime',
    ];
}
