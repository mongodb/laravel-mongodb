<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\DocumentModel;

/**
 * Mirrors the Queryable Encryption tutorial model.
 *
 * The encrypted fields are declared in the connection's
 * encryptedFieldsMap: ssn (equality), billing_amount (range, int) and the
 * billing object (randomized).
 */
class Patient extends Model
{
    use DocumentModel;

    protected $connection = 'mongodb';

    protected $table = 'patients';

    protected $fillable = ['ssn', 'billing_amount', 'billing'];

    protected $casts = [
        // The field is encrypted as an int per the encryptedFieldsMap; cast
        // so form/string input is stored as an integer.
        'billing_amount' => 'integer',
    ];
}
