<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumToken;
use MongoDB\Laravel\Eloquent\DocumentModel;

class PersonalAccessToken extends SanctumToken
{
    use DocumentModel;

    protected $connection = 'mongodb';
    protected $table = 'personal_access_tokens';
    protected $keyType = 'string';
}
