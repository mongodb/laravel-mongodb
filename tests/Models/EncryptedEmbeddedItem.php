<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Models;

use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Relations\EmbedsOne;

/**
 * The embedded document of {@see EncryptedEmbedded}, and of a nested relation.
 * It is stored in the same collection as its parent, so the encrypted fields of
 * its own attributes are declared in the encryptedFieldsMap of that collection.
 */
class EncryptedEmbeddedItem extends Model
{
    protected $connection = 'mongodb';
    protected $table = 'encrypted_embedded';
    protected static $unguarded = true;

    public function label(): EmbedsOne
    {
        return $this->embedsOne(self::class, 'label');
    }
}
