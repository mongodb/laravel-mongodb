<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use MongoDB\Laravel\Eloquent\Model;

final class FieldTypesCacheProbe extends Model
{
    protected $connection = 'mongodb';
}
