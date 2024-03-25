<?php

declare(strict_types=1);

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Cargo extends Model
{
    protected $connection = 'mongodb';
}
