<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use MongoDB\Laravel\Eloquent\Model as Eloquent;

class Guarded extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'guarded';
    protected $guarded    = ['foobar', 'level1->level2'];
}
