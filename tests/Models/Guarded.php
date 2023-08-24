<?php

declare(strict_types=1);

namespace Jenssegers\Mongodb\Tests\Models;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

class Guarded extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'guarded';
    protected $guarded = ['foobar', 'level1->level2'];
}
