<?php

namespace App\Models;

use MongoDB\Laravel\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $connection = 'mongodb';
    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];
}
