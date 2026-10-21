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

    /** The collection does not exist. */
    case Missing = 'missing';
}
