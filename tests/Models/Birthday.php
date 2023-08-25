<?php

declare(strict_types=1);

namespace Jenssegers\Mongodb\Tests\Models;

use Jenssegers\Mongodb\Eloquent\Model as Eloquent;

/**
 * Class Birthday.
 *
 * @property string $name
 * @property string $birthday
 * @property string $time
 */
class Birthday extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'birthday';
    protected $fillable = ['name', 'birthday'];

    protected $casts = [
        'birthday' => 'datetime',
    ];
}
