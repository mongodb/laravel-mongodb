# Queryable Encryption

Queryable Encryption (QE) is **automatic client-side field-level encryption** (also called automatic CSFLE). Encryption and decryption are performed by the MongoDB driver (libmongocrypt) and the server, transparently to the application. laravel-mongodb only provides **configuration**: the `encryptedFieldsMap`, the Schema DSL, and the Artisan commands. There is no model encryption metadata and no manual `encrypt()`/`decrypt()` call: the encryption work runs in libmongocrypt (native code reached through the driver) and on the server, not in a PHP loop. QE still has overhead (encryption, the `__safeContent__` array, extra indexes), just not in PHP userland.

Use QE when a PII field must also be **queryable** by the server (exact match or range). For opacity only, without server-side querying, use the Eloquent `encrypted` cast instead (it encrypts with the application key and stores an opaque blob).

Same view applies in `references/eloquent-models.md`: models stay plain, the `encryptedFieldsMap` is the single source of truth.

## Requirements

- MongoDB Enterprise Advanced or Atlas. Equality requires 7.0+, range requires 8.0+, on a replica set or sharded cluster.
- The Automatic Encryption Shared Library (`crypt_shared`), loaded by the driver.
- ext-mongodb 2.4.0+ when fields are referenced by their alternate key name, which is the default.

## Configure the connection

Encryption is configured once in `config/database.php`. The `encryptedFieldsMap` is the single source of truth for the client-side configuration; models carry no encryption metadata. The map is optional on the connection, because the server can supply the encryption configuration for server-side QE, but it is recommended for security and required to create an encrypted collection through this package (`mongodb:encryption:create-collection` or `Schema::createEncrypted()`).

```php
'driver_options' => [
    'autoEncryption' => [
        'keyVaultNamespace' => 'encryption.__keyVault',
        'kmsProviders' => [
            'local' => ['key' => base64_encode(random_bytes(96))],
        ],
        'extraOptions' => [
            'cryptSharedLibPath' => env('MONGODB_CRYPT_SHARED_LIB_PATH'),
            'cryptSharedLibRequired' => true,
        ],
        'encryptedFieldsMap' => [
            'patients' => [
                'fields' => [
                    'ssn' => ['bsonType' => 'string', 'queryType' => 'equality'],
                    'billing_amount' => ['bsonType' => 'int', 'queryType' => 'range', 'min' => 0, 'max' => 5000, 'sparsity' => 1],
                    'billing' => 'object',
                ],
            ],
        ],
    ],
],
```

- `keyVaultNamespace` and a non-empty `kmsProviders` are required. `cryptSharedLibRequired` defaults to `true`.
- `tlsOptions` holds the TLS settings of each KMS provider, keyed by provider name (`['aws' => ['tlsCAFile' => '/path/ca.pem']]`). It is needed for a KMS reachable only through a private CA: libmongocrypt always speaks HTTPS to a custom KMS endpoint, and there is no way to declare the CA otherwise.
- Two field syntaxes are accepted and normalized to the driver format: the list form above (keyed by path) and the verbose list form (`['fields' => [['path' => 'ssn', 'bsonType' => 'string', 'queries' => [['queryType' => 'equality']]]]]`).
- A bare string value (`'billing' => 'object'`) means a randomized, non-queryable field.
- Range fields take `queryType => 'range'` plus flat `min`, `max`, and `sparsity` options.

## Keys without base64 keyIds

Each field is bound to a data key by an **alternate key name**, not an opaque base64 `keyId`. The name defaults to `"<database>.<collection>/<path>"`, and can be overridden with a per-field `keyAltName` in the config. The driver resolves the name to the real keyId, or laravel-mongodb generates the key on first create.

## Create and diagnose

One command creates the encrypted collection, its metadata collections and indexes, and generates any missing data keys. It is idempotent: an existing encrypted collection is returned as is, and the data keys are reused by alternate key name.

```sh
php artisan mongodb:encryption:create-collection patients
php artisan mongodb:encryption:status
```

The collection argument is optional. Without it, every mapped collection that does not exist yet is created. In an interactive terminal they are proposed for selection first, all selected by default, so pressing enter creates them all.

`--recreate` drops the mapped collections that already exist before creating them again, to rebuild them from a changed map. It asks for confirmation in production, and takes `--force`.

Both commands accept `--no-server` to validate the configuration without a server. The Schema builder exposes the same creation:

```php
Schema::connection('mongodb')->createEncrypted('patients');
Schema::connection('mongodb')->hasEncryptedCollection('patients');
```

Collection names are logical: the connection `prefix` is applied, and the `encryptedFieldsMap` is matched on the real collection name. `Schema::drop()` on an encrypted collection also drops its metadata collections, so it can be created again.

Dropping the collection keeps the data keys, so you can recreate with the same map. __Dropping the whole database also drops the key vault and every data encryption key when the vault lives in that database. This is unrecoverable.__

## Rotate the master key

Each data encryption key (DEK) is itself stored encrypted in the key vault, under a master key held by a KMS provider. This command re-encrypts the DEKs under a new master key. The DEKs keep their id and key material, so no document is read or rewritten and encrypted data stays readable.

```sh
php artisan mongodb:encryption:rewrap-data-keys
php artisan mongodb:encryption:rewrap-data-keys --provider=aws --master-key='{"region":"eu-west-1","key":"arn:aws:kms:..."}'
```

| Option | Effect |
|---|---|
| `--provider` | The KMS provider to wrap the DEKs under. Defaults to the first entry of `kmsProviders`. |
| `--master-key` | The new master key as JSON, required for cloud KMS providers. Defaults to `autoEncryption.masterKey`. Omit it for `local`. |
| `--filter` | A JSON key vault query narrowing which DEKs are rewrapped. Defaults to all of them. |
| `--connection` | The MongoDB connection to use. |
| `--force` | Skip the confirmation prompt in production. |

Both the old and the new KMS credentials must be in `kmsProviders` when the command runs: the DEKs are unwrapped with the old master key before being rewrapped with the new one. The new credentials must stay in the configuration afterwards. Without them, every DEK, and so every encrypted document, is undecryptable. For two `local` keys, configure the new one as a [named KMS provider](https://www.mongodb.com/docs/manual/core/csfle/reference/kms-providers/) (`local:rotated`) and pass `--provider=local:rotated`.

A master key for a self-hosted KMS also carries an `endpoint`, a `host:port` without scheme. The certificate of that endpoint is trusted through `tlsOptions`, which is forwarded to the `ClientEncryption` used by this command.

This rewraps the DEKs rather than replacing them. Replacing the DEKs themselves is a different and far more expensive operation, which has to read, decrypt and rewrite every document, and is not supported by this package.

## `__safeContent__`

The server writes a reserved `__safeContent__` array. laravel-mongodb rejects any attempt to write it, by direct assignment, by mass assignment, or through a dotted sub-path. It is still returned by `toArray()` and JSON output.

## Match the bsonType

Automatic encryption encrypts a value according to the `bsonType` declared in the map, and the server **rejects a value that does not match** that type at write time. For every encrypted field, laravel-mongodb derives an Eloquent cast from the `bsonType` and coerces the value on write, so a form or JSON string becomes the expected type instead of being stored as a string or rejected. `$model->billing_amount = '1500'` stores the integer `1500`, and the value read back is an `int`.

| bsonType | Cast derived | Value stored |
|---|---|---|
| `string` | none | string |
| `int` | `integer` | 32-bit integer |
| `long` | `integer` | 64-bit integer |
| `double` | `float` | double |
| `decimal` | none | Decimal128 |
| `bool` | `boolean` | boolean |
| `date` | `datetime` | datetime |
| `object` | none | embedded document |
| `array` | none | encrypted array |

- A value that cannot be converted is passed to the driver unchanged, and automatic encryption rejects the write. The package adds no validation of its own. This covers `'abc'` on an `int` field and a `long` outside the signed 64-bit range, which the cast to a PHP integer would saturate. A numeric value written with surrounding whitespace, a leading `+`, or leading zeros is converted.
- No cast is derived for `decimal`: declare `decimal:<scale>` yourself, with an integer scale, to read the `Decimal128`. A BSON date holds a full timestamp, so the derived cast is `datetime`, which keeps the time.
- An embedded document is `bsonType: 'object'`, stored natively and read back as an array. Its encrypted sub-fields are declared as dotted paths (`billing.amount`) and coerced on write, including when the model is saved through an `EmbedsOne` relation. An `array` field is written as a whole, and its elements are not encrypted one by one. Automatic encryption refuses to encrypt a field under an array, so the documents of an `EmbedsMany` relation cannot hold encrypted fields.
- A cast declared on an encrypted field is kept when it stores the declared BSON type, as `integer` on an `int` field or `hashed` on a `string` field, and reported with a `LogicException` when it does not. A custom cast class owns its value, on a nested embedded model too.
- The coercion runs when the attribute is assigned, before a set mutator, and applies to values written through the model, not to query values: a range query on a `date` field needs date objects (Carbon), not strings.
- QE forbids multi-document updates. laravel-mongodb uses single-document updates automatically on encrypted collections, so `save()` and `update()` work as usual.

## Supported queries and limits

- Equality fields support equality queries; range fields (8.0+) also support `$lt`, `$lte`, `$gt`, `$gte`.
- `$text`, `$where`, and `$jsonSchema` are unsupported, even on unencrypted fields.
- An encrypted field cannot be compared to `null` or a regex.
- Queryable Encryption is incompatible with MongoDB Atlas Search; Scout integration cannot combine with QE on the same fields.
