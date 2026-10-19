<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\EmbedsOne;

/**
 * A model with an embedded relation whose encrypted fields are declared as
 * dotted paths in the encryptedFieldsMap of its collection.
 *
 * Only a relation that stores a single embedded document can hold encrypted
 * fields: automatic encryption refuses to encrypt a field under an array, so
 * the documents of an EmbedsMany relation cannot.
 */
class EncryptedEmbedded extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'encrypted_embedded';
    protected static $unguarded = true;

    public function address(): EmbedsOne
    {
        return $this->embedsOne(EncryptedEmbeddedItem::class);
    }
}
