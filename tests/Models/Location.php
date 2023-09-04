<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Location extends Eloquent
{
    protected $connection       = 'mongodb';
    protected $collection       = 'locations';
    protected static $unguarded = true;
}
