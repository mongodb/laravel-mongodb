<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use MongoDB\Laravel\Eloquent\Casts\AsBsonArray;
use MongoDB\Laravel\Eloquent\Casts\AsBsonDocument;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;

/**
 * @property BSONDocument $specs
 * @property BSONArray    $variants
 */
class BsonCasting extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'phplara81_bson_casts';
    protected $fillable = ['specs', 'variants'];

    protected $casts = [
        'specs' => AsBsonDocument::class,
        'variants' => AsBsonArray::class,
    ];
}
