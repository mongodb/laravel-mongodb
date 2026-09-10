<?php

declare(strict_types=1);

namespace MongoDB\Laravel;

use Composer\InstalledVersions;
use Illuminate\Database\Connection as BaseConnection;
use InvalidArgumentException;
use LogicException;
use MongoDB\BSON\Binary;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\Driver\ClientEncryption;
use MongoDB\Driver\Exception\AuthenticationException;
use MongoDB\Driver\Exception\ConnectionException;
use MongoDB\Driver\Exception\RuntimeException;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query as DriverQuery;
use MongoDB\Driver\ReadPreference;
use MongoDB\Laravel\Concerns\ManagesTransactions;
use OutOfBoundsException;
use Override;
use Throwable;

use function array_key_exists;
use function array_key_first;
use function array_values;
use function base64_decode;
use function filter_var;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function phpversion;
use function preg_match;
use function sprintf;
use function str_contains;
use function strlen;
use function trigger_error;
use function version_compare;

use const E_USER_DEPRECATED;
use const FILTER_FLAG_IPV6;
use const FILTER_VALIDATE_IP;

/** @mixin Database */
class Connection extends BaseConnection
{
    use ManagesTransactions;

    private static ?string $version = null;

    /**
     * The MongoDB database handler.
     *
     * @var Database
     */
    protected $db;

    /**
     * The MongoDB connection handler.
     *
     * @var Client
     */
    protected $connection;

    private ?CommandSubscriber $commandSubscriber = null;

    /**
     * A plain, non-auto-encrypted manager used to reach the key vault. The key
     * vault client must not be auto-encrypted (CSFLE requirement).
     *
     * @var Manager|null
     */
    private ?Manager $plainManager = null;

    /** @var bool Whether to rename the rename "id" into "_id" for embedded documents. */
    private bool $renameEmbeddedIdField;

    /**
     * Create a new database connection instance.
     */
    public function __construct(array $config)
    {
        $this->config = $config;

        // Build the connection string
        $dsn = $this->getDsn($config);

        // You can pass options directly to the MongoDB constructor
        $options = $config['options'] ?? [];

        // Create the connection
        $this->connection = $this->createConnection($dsn, $config, $options);
        $this->database = $this->getDefaultDatabaseName($dsn, $config);

        // Select database
        $this->db = $this->connection->getDatabase($this->database);

        $this->tablePrefix = $config['prefix'] ?? '';

        $this->useDefaultPostProcessor();

        $this->useDefaultSchemaGrammar();

        $this->useDefaultQueryGrammar();

        $this->renameEmbeddedIdField = $config['rename_embedded_id_field'] ?? true;
    }

    /**
     * Begin a fluent query against a database collection.
     *
     * @param  string      $table The name of the MongoDB collection
     * @param  string|null $as    Ignored. Not supported by MongoDB
     *
     * @return Query\Builder
     */
    #[Override]
    public function table($table, $as = null)
    {
        $query = new Query\Builder($this, $this->getQueryGrammar(), $this->getPostProcessor());

        return $query->from($table);
    }

    /**
     * Get a MongoDB collection.
     *
     * @param  string $name
     *
     * @return Collection
     */
    public function getCollection($name): Collection
    {
        return $this->db->selectCollection($this->tablePrefix . $name);
    }

    /** @inheritdoc */
    #[Override]
    public function getSchemaBuilder()
    {
        return new Schema\Builder($this);
    }

    /**
     * Get the MongoDB database object.
     *
     * @deprecated since mongodb/laravel-mongodb:5.2, use getDatabase() instead
     *
     * @return Database
     */
    public function getMongoDB()
    {
        trigger_error(sprintf('Since mongodb/laravel-mongodb:5.2, Method "%s()" is deprecated, use "getDatabase()" instead.', __FUNCTION__), E_USER_DEPRECATED);

        return $this->db;
    }

    /**
     * Get the MongoDB database object.
     *
     * @param string|null $name Name of the database, if not provided the default database will be returned.
     *
     * @return Database
     */
    public function getDatabase(?string $name = null): Database
    {
        if ($name && $name !== $this->database) {
            return $this->connection->getDatabase($name);
        }

        return $this->db;
    }

    /**
     * Return MongoDB object.
     *
     * @deprecated since mongodb/laravel-mongodb:5.2, use getClient() instead
     *
     * @return Client
     */
    public function getMongoClient()
    {
        trigger_error(sprintf('Since mongodb/laravel-mongodb:5.2, method "%s()" is deprecated, use "getClient()" instead.', __FUNCTION__), E_USER_DEPRECATED);

        return $this->getClient();
    }

    /**
     * Get the MongoDB client.
     */
    public function getClient(): ?Client
    {
        return $this->connection;
    }

    /** @inheritdoc  */
    #[Override]
    public function enableQueryLog()
    {
        parent::enableQueryLog();

        if (! $this->commandSubscriber) {
            $this->commandSubscriber = new CommandSubscriber($this);
            $this->connection->addSubscriber($this->commandSubscriber);
        }
    }

    #[Override]
    public function disableQueryLog()
    {
        parent::disableQueryLog();

        if ($this->commandSubscriber) {
            $this->connection->removeSubscriber($this->commandSubscriber);
            $this->commandSubscriber = null;
        }
    }

    #[Override]
    protected function withFreshQueryLog($callback)
    {
        try {
            return parent::withFreshQueryLog($callback);
        } finally {
            // The parent method enable query log using enableQueryLog()
            // but disables it by setting $loggingQueries to false. We need to
            // remove the subscriber for performance.
            if (! $this->loggingQueries) {
                $this->disableQueryLog();
            }
        }
    }

    /**
     * Get the name of the default database based on db config or try to detect it from dsn.
     *
     * @throws InvalidArgumentException
     */
    protected function getDefaultDatabaseName(string $dsn, array $config): string
    {
        if (empty($config['database'])) {
            if (! preg_match('/^mongodb(?:[+]srv)?:\\/\\/.+?\\/([^?&]+)/s', $dsn, $matches)) {
                throw new InvalidArgumentException('Database is not properly configured.');
            }

            $config['database'] = $matches[1];
        }

        return $config['database'];
    }

    /**
     * Create a new MongoDB connection.
     */
    protected function createConnection(string $dsn, array $config, array $options): Client
    {
        // By default driver options is an empty array.
        $driverOptions = [];

        if (isset($config['driver_options']) && is_array($config['driver_options'])) {
            $driverOptions = $config['driver_options'];
        }

        $driverOptions['driver'] = [
            'name' => 'laravel-mongodb',
            'version' => self::getVersion(),
        ];

        // Check if the credentials are not already set in the options
        if (! isset($options['username']) && ! empty($config['username'])) {
            $options['username'] = $config['username'];
        }

        if (! isset($options['password']) && ! empty($config['password'])) {
            $options['password'] = $config['password'];
        }

        if (isset($config['name'])) {
            $driverOptions += ['connectionName' => $config['name']];
        }

        // Normalize the automatic encryption configuration early. A broken
        // autoEncryption block fails fast at connection time. Capability
        // gating (library and server version) is lazy and only checked when
        // encryption is actually used, so unrelated non-QE features keep
        // working.
        $autoEncryption = null;
        if (isset($driverOptions['autoEncryption']) && is_array($driverOptions['autoEncryption'])) {
            $autoEncryption = $this->validateAutoEncryptionConfig($driverOptions['autoEncryption']);
            $driverOptions['autoEncryption'] = $autoEncryption;
        }

        // libmongocrypt (>= 1.18.0) resolves a field declared by "keyAltName"
        // to its real keyId from the key vault at runtime, so the package only
        // has to give every field a name (explicit, or "<collection>.<path>"
        // by default) and the driver performs the lookup. No key vault query
        // happens at connection time.
        if (is_array($autoEncryption) && is_array($autoEncryption['encryptedFieldsMap'] ?? null)) {
            $autoEncryption['encryptedFieldsMap'] = $this->normalizeEncryptedFieldsMap($autoEncryption['encryptedFieldsMap']);
            $this->ensureAltKeyNameSupport($autoEncryption['encryptedFieldsMap']);
            $driverOptions['autoEncryption'] = $autoEncryption;
        }

        return new Client($dsn, $options, $driverOptions);
    }

    /**
     * Verify the installed ext-mongodb can resolve encrypted fields by
     * keyAltName (libmongocrypt >= 1.18, i.e. ext-mongodb 2.4.0+). Only
     * checked when an alternate key name is actually used, so keyId-based and
     * non-encrypted configs stay compatible with older extensions.
     *
     * @param  array<string, mixed> $encryptedFieldsMap
     */
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

    /**
     * Compute the alternate key name to use for a field: the declared
     * keyAltName, or "<collection>.<path>" by default.
     *
     * @param  array<string, mixed> $field
     */
    private function keyAltNameFor(array $field, string $collection): string
    {
        if (isset($field['keyAltName']) && is_string($field['keyAltName']) && $field['keyAltName'] !== '') {
            return $field['keyAltName'];
        }

        return $collection . '.' . (string) $field['path'];
    }

    /**
     * Normalize the driver_options.autoEncryption.encryptedFieldsMap to the
     * driver's list form. Two field syntaxes are accepted:
     *
     * - A list of objects, each with an explicit "path":
     *   ['patients' => ['fields' => [['path' => 'ssn', 'bsonType' => 'string',
     *   'queries' => [['queryType' => 'equality']]]]]].
     *
     * - An object keyed by path, with a flat "queryType" and range options; a
     *   bare string value means a randomized, non-queryable field:
     *   ['patients' => ['fields' => ['ssn' => ['bsonType' => 'string',
     *   'queryType' => 'equality'], 'billing' => 'object']]].
     *
     * Each field also receives its alternate key name (declared, or
     * "<collection>.<path>" by default) when it does not carry a keyId.
     *
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
     * Normalize the "fields" of a single collection into the driver's list
     * form, accepting both the list and keyed-by-path syntaxes. A bare string
     * value is the bsonType of a randomized, non-queryable field. Each field
     * receives its alternate key name (declared, or "<collection>.<path>" by
     * default) when it does not carry a keyId.
     *
     * @param  array<mixed> $fields
     * @param  string       $collection
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeEncryptedFields(array $fields, string $collection): array
    {
        $normalized = [];

        foreach ($fields as $key => $config) {
            // Prefer an explicit path; otherwise the array key is the path.
            $path = is_array($config) && isset($config['path']) && is_string($config['path'])
                ? $config['path']
                : (is_string($key) ? $key : null);
            if ($path === null) {
                throw new LogicException(sprintf('Missing "path" for an encrypted field in collection "%s".', $collection));
            }

            // A bare string value is the bsonType of a randomized field.
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
                $queryType = $config['queryType'];
                if (! in_array($queryType, ['equality', 'range'], true)) {
                    throw new LogicException(sprintf('Invalid "queryType" "%s" for encrypted field "%s" in collection "%s": supported values are "equality" and "range".', $queryType, $path, $collection));
                }

                $query = ['queryType' => $queryType];
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
     * Resolve every field lacking a keyId, minting the data key when it does
     * not exist yet. This makes encrypted collection creation idempotent:
     * re-creating a dropped collection reuses the same keys.
     *
     * @param  array<string, mixed> $encryptedFieldsMap
     *
     * @return array<string, mixed>
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

                // The driver accepts only one of keyId or keyAltName on a
                // field; the alternate name has served its purpose.
                $field = ['keyId' => $keyId] + $field;
                unset($field['keyAltName']);
            }

            unset($field);

            $encryptedFields['fields'] = array_values($fields);
            $encryptedFieldsMap[$collection] = $encryptedFields;
        }

        return $encryptedFieldsMap;
    }

    /**
     * Look up the keyId of a data key by its alternate name in the key vault.
     * Uses a direct query rather than ClientEncryption::getKeyByAltName so it
     * works regardless of driver/server support for that helper.
     *
     * @param  string       $keyAltName
     * @param  Manager|null $manager
     */
    private function findDataKeyByAltName(string $keyAltName, ?Manager $manager = null): ?Binary
    {
        $namespace = $this->getConfig('driver_options.autoEncryption.keyVaultNamespace');

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

    /**
     * Determine whether automatic encryption (Queryable Encryption or CSFLE)
     * is requested on this connection.
     *
     * @param  array<string, mixed> $config
     */
    public function isEncryptionEnabled(array $config): bool
    {
        return ! empty($config['keyVaultNamespace']);
    }

    /**
     * Determine whether automatic encryption (Queryable Encryption or CSFLE)
     * is active on this connection.
     *
     * When a collection name is given, only collections mapped in the
     * encryptedFieldsMap are encrypted; the others keep their usual behavior.
     */
    public function isAutoEncryptionEnabled(?string $collection = null): bool
    {
        $config = $this->getConfig('driver_options.autoEncryption');

        if (! is_array($config) || ! $this->isEncryptionEnabled($config)) {
            return false;
        }

        if ($collection === null) {
            return true;
        }

        return isset($config['encryptedFieldsMap'][$collection]);
    }

    /**
     * Validate the shape of the driver_options.autoEncryption configuration and
     * return a normalized copy. This only checks the static configuration; it
     * never contacts the server.
     *
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

        // Validate the local master key, if the local provider is declared.
        $localProvider = $autoEncryption['kmsProviders']['local'] ?? null;
        if (is_array($localProvider)) {
            $key = $localProvider['key'] ?? null;
            if (! is_string($key)) {
                throw new InvalidArgumentException('The "autoEncryption.kmsProviders.local.key" value is required and must be a base64-encoded 96-byte master key.');
            }

            $decoded = base64_decode($key, true) ?: base64_decode($key);
            if ($decoded === false) {
                throw new InvalidArgumentException('The "autoEncryption.kmsProviders.local.key" value is not valid base64.');
            }

            if (strlen($decoded) !== 96) {
                throw new InvalidArgumentException('The "autoEncryption.kmsProviders.local.key" master key must decode to exactly 96 bytes.');
            }
        }

        // Normalize the crypt_shared settings. The shared library is required
        // for automatic encryption; default it to true unless explicitly
        // disabled and reject an explicitly null library path.
        $extraOptions = is_array($autoEncryption['extraOptions'] ?? null) ? $autoEncryption['extraOptions'] : [];
        $extraOptions += ['cryptSharedLibRequired' => true];

        $hasSearchPath = ! empty($extraOptions['cryptSharedLibPath'])
        || ! empty($extraOptions['cryptSharedSearchPath'])
        || ! empty($extraOptions['cryptSharedSearchPaths']);
        if ($extraOptions['cryptSharedLibRequired'] && ! $hasSearchPath && array_key_exists('cryptSharedLibPath', $extraOptions) && $extraOptions['cryptSharedLibPath'] === null) {
            throw new InvalidArgumentException('The "autoEncryption.extraOptions.cryptSharedLibPath" value cannot be null. Provide the path to the Automatic Encryption Shared Library or a search path.');
        }

        $autoEncryption['extraOptions'] = $extraOptions;

        // A collection mapped for automatic encryption must reference an
        // existing data encryption key. Point to the keys-first bootstrap.
        foreach (($autoEncryption['encryptedFieldsMap'] ?? []) as $collection => $encryptedFields) {
            if (! is_array($encryptedFields)) {
                continue;
            }

            foreach (($encryptedFields['fields'] ?? []) as $field) {
                if (is_array($field) && array_key_exists('keyId', $field) && $field['keyId'] === null) {
                    throw new InvalidArgumentException(sprintf('The encrypted field map for collection "%s" references a field with a null "keyId". Set a "keyAltName" or remove the "keyId" so it is resolved or minted automatically when the collection is created.', $collection));
                }
            }
        }

        return $autoEncryption;
    }

    /**
     * Get the client-side encryption support used to mint and manage data
     * encryption keys.
     *
     * This requires automatic encryption to be configured on the connection.
     *
     * @throws InvalidArgumentException when automatic encryption is not enabled.
     */
    public function getClientEncryption(): ClientEncryption
    {
        $autoEncryption = $this->getConfig('driver_options.autoEncryption');

        if (! is_array($autoEncryption) || ! $this->isEncryptionEnabled($autoEncryption)) {
            throw new InvalidArgumentException('Queryable Encryption is not enabled on this connection. Configure "driver_options.autoEncryption" with a "keyVaultNamespace" and "kmsProviders" first.');
        }

        $this->ensureQueryableEncryptionLibrary();

        $config = $this->validateAutoEncryptionConfig($autoEncryption);

        // The key vault client must be free of auto encryption (CSFLE rule),
        // so it cannot be the auto-encryption-enabled connection client.
        return $this->getClient()->createClientEncryption([
            'keyVaultClient' => $config['keyVaultClient'] ?? $this->plainManager(),
            'keyVaultNamespace' => $config['keyVaultNamespace'],
            'kmsProviders' => $config['kmsProviders'],
        ]);
    }

    /**
     * Get a cached plain (non-auto-encrypted) manager to the same server,
     * used only to reach the key vault.
     */
    private function plainManager(): Manager
    {
        return $this->plainManager ??= new Manager($this->getDsn($this->config), $this->config['options'] ?? []);
    }

    /**
     * Get the automatic encryption options configured on this connection.
     *
     * @return array<string, mixed>
     */
    public function getEncryptionOptions(): array
    {
        $autoEncryption = $this->getConfig('driver_options.autoEncryption');

        return is_array($autoEncryption) ? $autoEncryption : [];
    }

    /**
     * Ensure the installed mongodb/mongodb library is new enough to support
     * Queryable Encryption. This check is network-free and only fails when
     * encryption is actually requested.
     */
    private function ensureQueryableEncryptionLibrary(): void
    {
        try {
            $version = InstalledVersions::getPrettyVersion('mongodb/mongodb');
        } catch (Throwable) {
            return; // Unknown version; rely on the server to reject unsupported operations.
        }

        if (! is_string($version) || preg_match('/^(\d+)\.\d+\.\d+/', $version, $matches) !== 1) {
            return;
        }

        // These floors carry the metadata collection deletion fix the encrypted
        // collection lifecycle relies upon. Inlined because they are a runtime
        // implementation detail, not public API; dropped once the composer
        // constraint requires a newer mongodb/mongodb.
        $minimum = (int) $matches[1] === 2 ? '2.1.1' : '1.21.2';

        if (version_compare($version, $minimum, '<')) {
            throw new RuntimeException(sprintf('Queryable Encryption requires mongodb/mongodb %s or later. Installed version is %s.', $minimum, $version));
        }
    }

    /**
     * Check the connection to the MongoDB server
     *
     * @throws ConnectionException if connection to the server fails (for reasons other than authentication).
     * @throws AuthenticationException if authentication is needed and fails.
     * @throws RuntimeException if a server matching the read preference could not be found.
     */
    public function ping(): void
    {
        $this->getClient()->getManager()->selectServer(new ReadPreference(ReadPreference::PRIMARY_PREFERRED));
    }

    /** @inheritdoc */
    public function disconnect()
    {
        $this->disableQueryLog();
        $this->connection = null;
    }

    /**
     * Determine if the given configuration array has a dsn string.
     *
     * @deprecated
     */
    protected function hasDsnString(array $config): bool
    {
        return ! empty($config['dsn']);
    }

    /**
     * Get the DSN string form configuration.
     */
    protected function getDsnString(array $config): string
    {
        return $config['dsn'];
    }

    /**
     * Get the DSN string for a host / port configuration.
     */
    protected function getHostDsn(array $config): string
    {
        // Treat host option as array of hosts
        $hosts = is_array($config['host']) ? $config['host'] : [$config['host']];

        foreach ($hosts as &$host) {
            // ipv6
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $host = '[' . $host . ']';
                if (! empty($config['port'])) {
                    $host .= ':' . $config['port'];
                }
            } else {
                // Check if we need to add a port to the host
                if (! str_contains($host, ':') && ! empty($config['port'])) {
                    $host .= ':' . $config['port'];
                }
            }
        }

        // Check if we want to authenticate against a specific database.
        $authDatabase = isset($config['options']) && ! empty($config['options']['database']) ? $config['options']['database'] : null;

        return 'mongodb://' . implode(',', $hosts) . ($authDatabase ? '/' . $authDatabase : '');
    }

    /**
     * Create a DSN string from a configuration.
     */
    protected function getDsn(array $config): string
    {
        if (! empty($config['dsn'])) {
            return $this->getDsnString($config);
        }

        if (! empty($config['host'])) {
            return $this->getHostDsn($config);
        }

        throw new InvalidArgumentException('MongoDB connection configuration requires "dsn" or "host" key.');
    }

    /** @inheritdoc */
    #[Override]
    public function getDriverName()
    {
        return 'mongodb';
    }

    /** @inheritdoc */
    public function getDriverTitle()
    {
        return 'MongoDB';
    }

    /** @inheritdoc */
    #[Override]
    protected function getDefaultPostProcessor()
    {
        return new Query\Processor();
    }

    /** @inheritdoc */
    #[Override]
    protected function getDefaultQueryGrammar()
    {
        return new Query\Grammar($this);
    }

    /** @inheritdoc */
    #[Override]
    protected function getDefaultSchemaGrammar()
    {
        return new Schema\Grammar($this);
    }

    /**
     * Set database.
     */
    public function setDatabase(Database $db)
    {
        $this->db = $db;
    }

    /** @inheritdoc  */
    public function threadCount()
    {
        $status = $this->db->command(['serverStatus' => 1])->toArray();

        return $status[0]['connections']['current'];
    }

    /**
     * Dynamically pass methods to the connection.
     *
     * @param  string $method
     * @param  array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->db->$method(...$parameters);
    }

    /** Set whether to rename "id" field into "_id" for embedded documents. */
    public function setRenameEmbeddedIdField(bool $rename): void
    {
        $this->renameEmbeddedIdField = $rename;
    }

    /** Get whether to rename "id" field into "_id" for embedded documents. */
    public function getRenameEmbeddedIdField(): bool
    {
        return $this->renameEmbeddedIdField;
    }

    /**
     * Return the server version of one of the MongoDB servers: primary for
     * replica sets and standalone, and the selected server for sharded clusters.
     *
     * @internal
     */
    public function getServerVersion(): string
    {
        return $this->db->command(['buildInfo' => 1])->toArray()[0]['version'];
    }

    private static function getVersion(): string
    {
        return self::$version ?? self::lookupVersion();
    }

    private static function lookupVersion(): string
    {
        try {
            try {
                return self::$version = InstalledVersions::getPrettyVersion('mongodb/laravel-mongodb') ?? 'unknown';
            } catch (OutOfBoundsException) {
                return self::$version = InstalledVersions::getPrettyVersion('jenssegers/mongodb') ?? 'unknown';
            }
        } catch (Throwable) {
            return self::$version = 'error';
        }
    }
}
