<?php

declare(strict_types=1);

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Account extends Model
{
    protected $connection = 'mongodb';
    protected $fillable = ['number', 'balance'];
}
