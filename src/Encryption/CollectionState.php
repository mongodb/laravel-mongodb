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

    /** The collection exists without encrypted fields, which are stored in plaintext. */
    case NotEncrypted = 'exists, not encrypted';

    /**
     * The collection is an encrypted collection, but its encrypted fields are
     * not the ones declared in the configuration: a field declared since the
     * collection was created is stored in plaintext, and a field removed from
     * the configuration is no longer readable.
     */
    case Mismatched = 'exists, fields differ';

    /** The collection does not exist. */
    case Missing = 'missing';
}
