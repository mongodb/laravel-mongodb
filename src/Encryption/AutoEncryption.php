<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Encryption;

use InvalidArgumentException;
use LogicException;
use MongoDB\BSON\Binary;
use MongoDB\Driver\ClientEncryption;
use MongoDB\Driver\Exception\RuntimeException;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query as DriverQuery;
use MongoDB\Laravel\Connection;
use WeakReference;

use function array_key_exists;
use function array_key_first;
use function array_values;
use function base64_decode;
use function is_array;
use function is_string;
use function phpversion;
use function sprintf;
use function str_contains;
use function strlen;
use function version_compare;

/**
 * Queryable Encryption support, owned by a Connection which exposes the public
 * surface.
 *
 * @internal
 */
final class AutoEncryption
{
    private ?Manager $plainManager = null;

    /** @var WeakReference<Connection> */
    private readonly WeakReference $connection;

    public function __construct(Connection $connection, private readonly string $dsn, private readonly array $config)
    {
        $this->connection = WeakReference::create($connection);
    }

    private function connection(): Connection
    {
        return $this->connection->get() ?? throw new LogicException('The owning Connection is no longer available.');
    }

    /**
     * @param  array<string, mixed> $driverOptions
     *
     * @return array<string, mixed>
     */
    public function prepareDriverOptions(array $driverOptions): array
    {
        $autoEncryption = $driverOptions['autoEncryption'] ?? null;

        if (! is_array($autoEncryption)) {
            return $driverOptions;
        }

        $autoEncryption = $this->validateAutoEncryptionConfig($autoEncryption);

        if (is_array($autoEncryption['encryptedFieldsMap'] ?? null)) {
            $autoEncryption['encryptedFieldsMap'] = $this->normalizeEncryptedFieldsMap($autoEncryption['encryptedFieldsMap']);
            $this->ensureAltKeyNameSupport($autoEncryption['encryptedFieldsMap']);
        }

        $driverOptions['autoEncryption'] = $autoEncryption;

        return $driverOptions;
    }

    /** @param array<string, mixed> $config */
    public function isEncryptionEnabled(array $config): bool
    {
        return ! empty($config['keyVaultNamespace']);
    }

    public function isAutoEncryptionEnabled(?string $collection = null): bool
    {
        $config = $this->connection()->getConfig('driver_options.autoEncryption');

        if (! is_array($config) || ! $this->isEncryptionEnabled($config)) {
            return false;
        }

        if ($collection === null) {
            return true;
        }

        return isset($config['encryptedFieldsMap'][$collection]);
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws InvalidArgumentException
     */
    public function encryptedFieldsFor(string $collection): array
    {
        $config = $this->connection()->getConfig('driver_options.autoEncryption');

        if (! is_array($config) || ! $this->isEncryptionEnabled($config)) {
            throw new InvalidArgumentException('Queryable Encryption is not enabled on this connection. Configure "driver_options.autoEncryption" with a "keyVaultNamespace" and "kmsProviders" first.');
        }

        if (! is_array($config['encryptedFieldsMap'] ?? null)) {
            throw new InvalidArgumentException('No "encryptedFieldsMap" is configured on this connection to create an encrypted collection.');
        }

        $normalizedMap = $this->normalizeEncryptedFieldsMap($config['encryptedFieldsMap']);
        $map = $normalizedMap[$collection] ?? null;
        if (! is_array($map) || ! isset($map['fields']) || ! is_array($map['fields'])) {
            throw new InvalidArgumentException(sprintf('No "encryptedFieldsMap[%s]" entry is configured on this connection to create an encrypted collection.', $collection));
        }

        return $map['fields'];
    }

    /**
     * @param  array<string, mixed> $autoEncryption
     *
     * @return array<string, mixed>
     */
    public function validateAutoEncryptionConfig(array $autoEncryption): array
    {
        if (empty($autoEncryption['keyVaultNamespace']) || ! is_string($autoEncryption['keyVaultNamespace'])) {
            throw new InvalidArgumentException('The "autoEncryption.keyVaultNamespace" driver option is required to use Queryable Encryption. Configure "database.connections.<name>.driver_options.autoEncryption.keyVaultNamespace".');
        }

        if (empty($autoEncryption['kmsProviders']) || ! is_array($autoEncryption['kmsProviders'])) {
            throw new InvalidArgumentException('The "autoEncryption.kmsProviders" driver option must be a non-empty array to use Queryable Encryption.');
        }

        $localProvider = $autoEncryption['kmsProviders']['local'] ?? null;
        if (is_array($localProvider)) {
            $key = $localProvider['key'] ?? null;
            if (! is_string($key)) {
                throw new InvalidArgumentException('The "autoEncryption.kmsProviders.local.key" value is required and must be a base64-encoded 96-byte master key.');
            }

            $decoded = base64_decode($key, true);
            if ($decoded === false) {
                throw new InvalidArgumentException('The "autoEncryption.kmsProviders.local.key" value is not valid base64.');
            }

            if (strlen($decoded) !== 96) {
                throw new InvalidArgumentException('The "autoEncryption.kmsProviders.local.key" master key must decode to exactly 96 bytes.');
            }
        }

        $extraOptions = is_array($autoEncryption['extraOptions'] ?? null) ? $autoEncryption['extraOptions'] : [];
        $extraOptions += ['cryptSharedLibRequired' => true];

        $hasSearchPath = ! empty($extraOptions['cryptSharedLibPath'])
        || ! empty($extraOptions['cryptSharedSearchPath'])
        || ! empty($extraOptions['cryptSharedSearchPaths']);
        if ($extraOptions['cryptSharedLibRequired'] && ! $hasSearchPath && array_key_exists('cryptSharedLibPath', $extraOptions) && $extraOptions['cryptSharedLibPath'] === null) {
            throw new InvalidArgumentException('The "autoEncryption.extraOptions.cryptSharedLibPath" value cannot be null. Provide the path to the Automatic Encryption Shared Library or a search path.');
        }

        $autoEncryption['extraOptions'] = $extraOptions;

        foreach (($autoEncryption['encryptedFieldsMap'] ?? []) as $collection => $encryptedFields) {
            if (! is_array($encryptedFields)) {
                continue;
            }

            foreach (($encryptedFields['fields'] ?? []) as $field) {
                if (is_array($field) && array_key_exists('keyId', $field) && $field['keyId'] === null) {
                    throw new InvalidArgumentException(sprintf('The encrypted field map for collection "%s" references a field with a null "keyId". Set a "keyAltName" or remove the "keyId" so it is resolved or generated automatically when the collection is created.', $collection));
                }
            }
        }

        return $autoEncryption;
    }

    /**
     * @param  array<string, mixed> $encryptedFieldsMap
     *
     * @return array<string, mixed>
     */
    public function normalizeEncryptedFieldsMap(array $encryptedFieldsMap): array
    {
        foreach ($encryptedFieldsMap as $collection => $encryptedFields) {
            $fields = $encryptedFields['fields'] ?? null;

            if (! is_array($fields)) {
                throw new LogicException(sprintf('The encrypted fields map entry for collection "%s" must define a "fields" array.', $collection));
            }

            $encryptedFieldsMap[$collection]['fields'] = $this->normalizeEncryptedFields($fields, (string) $collection);
        }

        return $encryptedFieldsMap;
    }

    /**
     * @param  array<mixed> $fields
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeEncryptedFields(array $fields, string $collection): array
    {
        $normalized = [];

        foreach ($fields as $key => $config) {
            $path = is_array($config) && isset($config['path']) && is_string($config['path'])
                ? $config['path']
                : (is_string($key) ? $key : null);
            if ($path === null) {
                throw new LogicException(sprintf('Missing "path" for an encrypted field in collection "%s".', $collection));
            }

            if (is_string($config)) {
                $config = ['bsonType' => $config];
            }

            if (! is_array($config)) {
                throw new LogicException(sprintf('Invalid encrypted field for path "%s" in collection "%s": expected a string bsonType or an array.', $path, $collection));
            }

            $bsonType = $config['bsonType'] ?? null;
            if (! is_string($bsonType) || $bsonType === '') {
                throw new LogicException(sprintf('Missing or invalid "bsonType" for encrypted field "%s" in collection "%s".', $path, $collection));
            }

            if (isset($config['keyId']) && isset($config['keyAltName'])) {
                throw new LogicException(sprintf('Encrypted field "%s" in collection "%s" cannot declare both "keyId" and "keyAltName".', $path, $collection));
            }

            $field = ['path' => $path, 'bsonType' => $bsonType];

            if (isset($config['keyId'])) {
                $field['keyId'] = $config['keyId'];
            }

            if (isset($config['queries']) && is_array($config['queries'])) {
                $field['queries'] = $config['queries'];
            } elseif (isset($config['queryType'])) {
                $query = ['queryType' => $config['queryType']];
                foreach (['min', 'max', 'sparsity', 'precision'] as $option) {
                    if (array_key_exists($option, $config)) {
                        $query[$option] = $config[$option];
                    }
                }

                $field['queries'] = [$query];
            }

            if (isset($config['keyAltName']) && is_string($config['keyAltName']) && $config['keyAltName'] !== '') {
                $field['keyAltName'] = $config['keyAltName'];
            } elseif (! array_key_exists('keyId', $field)) {
                $field['keyAltName'] = $this->keyAltNameFor($field, $collection);
            }

            $normalized[] = $field;
        }

        return $normalized;
    }

    /**
     * Default alternate key name: "<database>.<collection>/<path>". See
     * DRIVERS-3637.
     *
     * @param  array<string, mixed> $field
     */
    private function keyAltNameFor(array $field, string $collection): string
    {
        if (isset($field['keyAltName']) && is_string($field['keyAltName']) && $field['keyAltName'] !== '') {
            return $field['keyAltName'];
        }

        return $this->connection()->getDatabaseName() . '.' . $collection . '/' . $field['path'];
    }

    /** @throws InvalidArgumentException */
    public function getClientEncryption(): ClientEncryption
    {
        $autoEncryption = $this->connection()->getConfig('driver_options.autoEncryption');

        if (! is_array($autoEncryption) || ! $this->isEncryptionEnabled($autoEncryption)) {
            throw new InvalidArgumentException('Queryable Encryption is not enabled on this connection. Configure "driver_options.autoEncryption" with a "keyVaultNamespace" and "kmsProviders" first.');
        }

        // The key vault client must be free of auto encryption (CSFLE rule).
        return $this->connection()->getClient()->createClientEncryption([
            'keyVaultClient' => $autoEncryption['keyVaultClient'] ?? $this->plainManager(),
            'keyVaultNamespace' => $autoEncryption['keyVaultNamespace'],
            'kmsProviders' => $autoEncryption['kmsProviders'],
        ]);
    }

    /** @return array<string, mixed> */
    public function getEncryptionOptions(): array
    {
        $autoEncryption = $this->connection()->getConfig('driver_options.autoEncryption');

        return is_array($autoEncryption) ? $autoEncryption : [];
    }

    /**
     * @param  array<string, array{fields: list<array<string, mixed>>}> $encryptedFieldsMap
     *
     * @return array<string, array{
     *     fields: list<array{
     *         path: string,
     *         bsonType: string,
     *         keyId: Binary,
     *         queries?: list<array<string, mixed>>,
     *     }>,
     * }>
     */
    public function resolveOrCreateEncryptionKeys(array $encryptedFieldsMap): array
    {
        $config = $this->getEncryptionOptions();
        $clientEncryption = $this->getClientEncryption();
        $kmsProvider = array_key_first($config['kmsProviders']) ?: 'local';

        foreach ($encryptedFieldsMap as $collection => $encryptedFields) {
            $fields = $encryptedFields['fields'] ?? [];

            foreach ($fields as &$field) {
                if (! is_array($field) || isset($field['keyId'])) {
                    continue;
                }

                $keyAltName = $this->keyAltNameFor($field, $collection);
                $existing = $this->findDataKeyByAltName($keyAltName);

                $keyId = $existing instanceof Binary
                    ? $existing
                    : $clientEncryption->createDataKey($kmsProvider, ['keyAltNames' => [$keyAltName]]);

                $field = ['keyId' => $keyId] + $field;
                unset($field['keyAltName']);
            }

            unset($field);

            $encryptedFields['fields'] = array_values($fields);
            $encryptedFieldsMap[$collection] = $encryptedFields;
        }

        return $encryptedFieldsMap;
    }

    private function findDataKeyByAltName(string $keyAltName, ?Manager $manager = null): ?Binary
    {
        $namespace = $this->connection()->getConfig('driver_options.autoEncryption.keyVaultNamespace');

        if (! is_string($namespace) || ! str_contains($namespace, '.')) {
            return null;
        }

        $query = new DriverQuery(['keyAltNames' => $keyAltName]);
        $cursor = ($manager ?? $this->plainManager())->executeQuery($namespace, $query);

        foreach ($cursor as $document) {
            $id = $document->_id ?? null;
            if ($id instanceof Binary) {
                return $id;
            }
        }

        return null;
    }

    private function plainManager(): Manager
    {
        if ($this->plainManager === null) {
            $options = $this->config['options'] ?? [];

            if (! isset($options['username']) && ! empty($this->config['username'])) {
                $options['username'] = $this->config['username'];
            }

            if (! isset($options['password']) && ! empty($this->config['password'])) {
                $options['password'] = $this->config['password'];
            }

            $driverOptions = [
                'driver' => ['name' => 'laravel-mongodb', 'version' => Connection::getVersion()],
            ];

            $this->plainManager = new Manager($this->dsn, $options, $driverOptions);
        }

        return $this->plainManager;
    }

    /** @param array<string, mixed> $encryptedFieldsMap */
    private function ensureAltKeyNameSupport(array $encryptedFieldsMap): void
    {
        foreach ($encryptedFieldsMap as $encryptedFields) {
            foreach (($encryptedFields['fields'] ?? []) as $field) {
                if (! is_array($field) || isset($field['keyId'])) {
                    continue;
                }

                $version = phpversion('mongodb');

                if (is_string($version) && version_compare($version, '2.4.0', '<')) {
                    throw new RuntimeException(sprintf('Referencing encrypted fields by keyAltName requires ext-mongodb 2.4.0 or later (libmongocrypt >= 1.18). Installed version is %s.', $version));
                }

                return;
            }
        }
    }
}
