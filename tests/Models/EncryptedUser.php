<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\DocumentModel;

/**
 * Mirrors the encrypted user of the Queryable Encryption tutorial.
 *
 * The encrypted fields are declared in the connection's encryptedFieldsMap:
 * name (randomized), email (equality), phone (equality) and date_of_birth
 * (range, date). The password stays hashed, not encrypted.
 */
class EncryptedUser extends Model
{
    use DocumentModel;

    protected $connection = 'mongodb';

    protected $table = 'users';

    protected $fillable = ['name', 'email', 'phone', 'date_of_birth', 'password'];

    protected $hidden = ['password', 'remember_token'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'password' => 'hashed',
        ];
    }
}
