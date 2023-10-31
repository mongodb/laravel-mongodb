<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use MongoDB\Laravel\Eloquent\Casts\BinaryUuid;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Casting extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'casting';

    protected $fillable = [
        'uuid',
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
        'dateWithFormatField',
        'immutableDateField',
        'datetimeField',
        'datetimeWithFormatField',
    ];

    protected $casts = [
        'uuid' => BinaryUuid::class,
        'intNumber' => 'int',
        'floatNumber' => 'float',
        'decimalNumber' => 'decimal:2',
        'stringContent' => 'string',
        'booleanValue' => 'boolean',
        'objectValue' => 'object',
        'jsonValue' => 'json',
        'collectionValue' => 'collection',
        'dateField' => 'date',
        'dateWithFormatField' => 'date:j.n.Y H:i',
        'immutableDateField' => 'immutable_date',
        'datetimeField' => 'datetime',
        'datetimeWithFormatField' => 'datetime:j.n.Y H:i',
    ];
}
