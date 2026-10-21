<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Encryption;

/**
 * The state of a collection declared in the encryptedFieldsMap. The value of
 * each case is the label printed by "mongodb:encryption:status".
 *
 * @internal
 */
enum CollectionState: string
{
    /** The collection is an encrypted collection. */
    case Encrypted = 'exists';

    /**
     * The collection exists without encrypted fields. The documents written so
     * far may hold the mapped fields in plaintext.
     */
    case NotEncrypted = 'exists, not encrypted';

    /**
     * The collection is an encrypted collection, but its encrypted fields are
     * not the ones declared in the configuration. Its documents are encrypted
     * with the stored fields, and the collection has to be dropped and created
     * again to apply the declared ones.
     */
    case Mismatched = 'exists, fields differ';

    /** The collection does not exist. */
    case Missing = 'missing';
}
