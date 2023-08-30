<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;
use MongoDB\Laravel\Eloquent\SoftDeletes;

/**
 * Class Soft.
 *
 * @property \Carbon\Carbon $deleted_at
 */
class Soft extends Eloquent
{
    use SoftDeletes;

    protected $connection = 'mongodb';
    protected $collection = 'soft';
    protected static $unguarded = true;
    protected $casts = ['deleted_at' => 'datetime'];
}
