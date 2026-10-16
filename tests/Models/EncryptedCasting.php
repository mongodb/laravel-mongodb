<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Encrypted fields covering every supported bsonType, with no developer-declared
 * cast, so the casts and the write coercion are derived from the connection's
 * encryptedFieldsMap. The "billing" embedded document holds encrypted fields
 * declared as dotted paths.
 */
class EncryptedCasting extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'encrypted_casting';

    protected $fillable = [
        'an_int',
        'a_long',
        'a_double',
        'a_decimal',
        'a_bool',
        'a_date',
        'an_object',
        'a_string',
        'billing',
    ];
}
