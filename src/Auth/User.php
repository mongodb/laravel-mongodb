<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Auth;

use Illuminate\Foundation\Auth\User as BaseUser;
use MongoDB\Laravel\Eloquent\DocumentModel;

class User extends BaseUser
{
    use DocumentModel;

    protected $keyType = 'string';
}
