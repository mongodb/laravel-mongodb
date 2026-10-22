<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Declares the "collection" property that named the collection before the package
 * moved to Eloquent's "table". The property is ignored, and the model falls back
 * to the default collection name.
 */
class LegacyCollection extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'legacy_collection';
    protected static $unguarded = true;
}
