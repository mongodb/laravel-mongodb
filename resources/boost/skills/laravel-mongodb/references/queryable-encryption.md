# Queryable Encryption

Queryable Encryption (QE) is **automatic client-side field-level encryption** (also called automatic CSFLE). Encryption and decryption are performed by the MongoDB driver (libmongocrypt) and the server, transparently to the application. laravel-mongodb only provides **configuration**: the `encryptedFieldsMap`, the Schema DSL, and the Artisan commands. There is no model encryption metadata and no manual `encrypt()`/`decrypt()` call: the encryption work runs in libmongocrypt (native code reached through the driver) and on the server, not in a PHP loop. QE still has overhead (encryption, the `__safeContent__` array, extra indexes), just not in PHP userland.

Use QE when a PII field must also be **queryable** by the server (exact match or range). For opacity only, without server-side querying, use the Eloquent `encrypted` cast instead (it encrypts with the application key and stores an opaque blob).

Same view applies in `references/eloquent-models.md`: models stay plain, the `encryptedFieldsMap` is the single source of truth.

## Requirements

- MongoDB Enterprise Advanced or Atlas. Equality requires 7.0+, range requires 8.0+, on a replica set or sharded cluster.
- The Automatic Encryption Shared Library (`crypt_shared`), loaded by the driver.
- ext-mongodb 2.4.0+ when fields are referenced by their alternate key name, which is the default.

## Configure the connection

Encryption is configured once in `config/database.php`. The `encryptedFieldsMap` is the single source of truth for the client-side configuration; models carry no encryption metadata. The map is optional on the connection, because the server can supply the encryption configuration for server-side QE, but it is recommended for security and required to create an encrypted collection through this package (`mongodb:encrypted:create` or `Schema::createEncrypted()`).

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
- Two field syntaxes are accepted and normalized to the driver format: the list form above (keyed by path) and the verbose list form (`['fields' => [['path' => 'ssn', 'bsonType' => 'string', 'queries' => [['queryType' => 'equality']]]]]`).
- A bare string value (`'billing' => 'object'`) means a randomized, non-queryable field.
- Range fields take `queryType => 'range'` plus flat `min`, `max`, and `sparsity` options.

## Keys without base64 keyIds

Each field is bound to a data key by an **alternate key name**, not an opaque base64 `keyId`. The name defaults to `"<database>.<collection>/<path>"`, and can be overridden with a per-field `keyAltName` in the config. The driver resolves the name to the real keyId, or laravel-mongodb generates the key on first create.

## Create and diagnose

One command creates the encrypted collection, its metadata collections and indexes, and generates any missing data keys. It is idempotent by alternate key name.

```sh
php artisan mongodb:encrypted:create patients
php artisan mongodb:encrypted:diagnose
```

Both accept `--no-server` to validate the configuration without a server. The Schema builder exposes the same creation:

```php
Schema::connection('mongodb')->createEncrypted('patients');
```

Dropping the collection keeps the data keys, so you can recreate with the same map. __Dropping the whole database also drops the key vault and every data encryption key when the vault lives in that database. This is unrecoverable.__

## `__safeContent__`

The server writes a reserved `__safeContent__` array. laravel-mongodb hides it from `toArray()` and JSON output, and rejects any attempt to write it.

## Match the bsonType

Automatic encryption encrypts a value according to the `bsonType` declared in the map, and the server **rejects a value that does not match** that type at write time. Fail fast, no coercion is done by the package.

- HTTP form fields are strings, so coerce them to the mapped type before saving (`(int) $request->field`, a custom `CastsAttributes::set()` cast, a mutator, or `Rule::integer`). A plain `integer` Eloquent cast is read/serialization-side only.
- An embedded document must be `bsonType: 'object'`, not `'array'`. Do not `array`-cast an object field: it stores a JSON string that automatic encryption rejects.
- A range query on a `date` field needs date objects (Carbon), not strings.
- QE forbids multi-document updates. laravel-mongodb uses single-document updates automatically on encrypted collections, so `save()` and `update()` work as usual.

## Supported queries and limits

- Equality fields support equality queries; range fields (8.0+) also support `$lt`, `$lte`, `$gt`, `$gte`.
- `$text`, `$where`, and `$jsonSchema` are unsupported, even on unencrypted fields.
- An encrypted field cannot be compared to `null` or a regex.
- Queryable Encryption is incompatible with MongoDB Atlas Search; Scout integration cannot combine with QE on the same fields.
