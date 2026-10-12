<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests;

use Illuminate\Foundation\Application;
use MongoDB\Driver\Exception\ConnectionException;
use MongoDB\Driver\Exception\ConnectionTimeoutException;
use MongoDB\Driver\Exception\ServerException;
use MongoDB\Laravel\MongoDBServiceProvider;
use MongoDB\Laravel\Schema\Builder;
use MongoDB\Laravel\Tests\Models\User;
use MongoDB\Laravel\Validation\ValidationServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

use function base64_encode;
use function config;
use function env;
use function exec;
use function function_exists;
use function is_file;
use function is_string;
use function phpversion;
use function sprintf;
use function str_pad;
use function version_compare;

class TestCase extends OrchestraTestCase
{
    /** Test-only local master key. Not a secret: it only wraps throwaway test data keys. */
    private const LOCAL_MASTER_KEY = 'laravel-mongodb-queryable-encryption-test-key';

    /**
     * Get package providers.
     *
     * @param  Application $app
     */
    protected function getPackageProviders($app): array
    {
        return [
            MongoDBServiceProvider::class,
            ValidationServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  Application $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        // reset base path to point to our package's src directory
        //$app['path.base'] = __DIR__ . '/../src';

        $config = require 'config/database.php';

        $app['config']->set('app.key', 'ZsZewWyUJ5FsKp9lMwv4tYbNlegQilM7');

        $app['config']->set('database.default', 'mongodb');
        $app['config']->set('database.connections.sqlite', $config['connections']['sqlite']);
        $app['config']->set('database.connections.mongodb', $config['connections']['mongodb']);
        $app['config']->set('database.connections.mongodb2', $config['connections']['mongodb']);

        $app['config']->set('auth.model', User::class);
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('cache.driver', 'array');

        $app['config']->set('cache.stores.mongodb', [
            'driver' => 'mongodb',
            'connection' => 'mongodb',
            'collection' => 'foo_cache',
        ]);

        $app['config']->set('queue.default', 'database');
        $app['config']->set('queue.connections.database', [
            'driver' => 'mongodb',
            'table' => 'jobs',
            'queue' => 'default',
            'expire' => 60,
        ]);
        $app['config']->set('queue.failed.database', 'mongodb2');
        $app['config']->set('queue.failed.driver', 'mongodb');
    }

    public function skipIfSearchIndexManagementIsNotSupported(): void
    {
        if (! $this->isSearchIndexManagementSupported()) {
            self::markTestSkipped('Search index management is not supported on this server');
        }
    }

    public function skipIfSearchIndexManagementIsSupported(): void
    {
        if ($this->isSearchIndexManagementSupported()) {
            self::markTestSkipped('Search index management is supported on this server');
        }
    }

    private function isSearchIndexManagementSupported(): bool
    {
        try {
            $this->getConnection('mongodb')->getCollection('test')->listSearchIndexes(['name' => 'just_for_testing']);
        } catch (ServerException $e) {
            if (Builder::isAtlasSearchNotSupportedException($e)) {
                return false;
            }

            throw $e;
        }

        return true;
    }

    /**
     * Build autoEncryption driver options. The Automatic Encryption Shared
     * Library is used when the environment provides it, otherwise the driver
     * falls back to mongocryptd.
     *
     * The local master key is fixed, not random: the data keys it wraps stay
     * in the key vault between runs, and a fresh master key would make them
     * undecryptable on the next run.
     *
     * @param  array<string, mixed> $encryptedFieldsMap
     *
     * @return array<string, mixed>
     */
    protected function encryptionOptions(array $encryptedFieldsMap): array
    {
        $library = env('CRYPT_SHARED_LIB_PATH');

        $extraOptions = is_string($library) && $library !== ''
            ? ['cryptSharedLibPath' => $library, 'cryptSharedLibRequired' => true]
            : ['cryptSharedLibRequired' => false];

        return [
            'keyVaultNamespace' => 'encryption.__keyVault',
            'kmsProviders' => $this->localKmsProviders(),
            'extraOptions' => $extraOptions,
            'encryptedFieldsMap' => $encryptedFieldsMap,
        ];
    }

    /**
     * The local KMS provider used by every test that touches the shared key
     * vault. It must be the same across the whole test run: a data key created
     * under one master key cannot be reused under another.
     *
     * @return array<string, array{key: string}>
     */
    protected function localKmsProviders(): array
    {
        return ['local' => ['key' => base64_encode(str_pad(self::LOCAL_MASTER_KEY, 96, '0'))]];
    }

    /**
     * Enable Queryable Encryption on the default "mongodb" connection and
     * forget the cached connection so the new configuration is picked up.
     *
     * @param array<string, mixed> $encryptedFieldsMap
     */
    protected function enableEncryption(array $encryptedFieldsMap): void
    {
        config([
            'database.connections.mongodb.driver_options.autoEncryption' => $this->encryptionOptions($encryptedFieldsMap),
        ]);

        $this->app->make('db')->purge('mongodb');
    }

    /**
     * Skip when Queryable Encryption is not usable: the extension and an
     * automatic encryption runtime must be available, the encryptedFieldsMap
     * must be configured, and the server must be recent enough (8.0+ for range
     * and Community support) and a replica set or a sharded cluster.
     *
     * The extension and runtime checks run before the connection is resolved,
     * because building the connection raises when the extension is too old.
     */
    public function skipIfQEIsNotSupported(): void
    {
        $extensionVersion = phpversion('mongodb');

        if (is_string($extensionVersion) && version_compare($extensionVersion, '2.4.0', '<')) {
            self::markTestSkipped(sprintf('Queryable Encryption fields are referenced by keyAltName, which requires ext-mongodb 2.4.0 or later. Installed version is %s.', $extensionVersion));
        }

        if (! $this->hasEncryptionRuntime()) {
            self::markTestSkipped('No automatic encryption runtime is available. Set CRYPT_SHARED_LIB_PATH to the Automatic Encryption Shared Library, or install mongocryptd (MongoDB Enterprise).');
        }

        $connection = $this->getConnection('mongodb');

        if (! $connection->isAutoEncryptionEnabled('patients') && ! $connection->isAutoEncryptionEnabled('users')) {
            self::markTestSkipped('Queryable Encryption is not configured on the "mongodb" connection.');
        }

        try {
            $version = $connection->getServerVersion();
        } catch (ConnectionException | ConnectionTimeoutException) {
            self::markTestSkipped('A MongoDB server is not reachable.');
        }

        if (version_compare($version, '8.0', '<')) {
            self::markTestSkipped('Queryable Encryption requires MongoDB 8.0 or later.');
        }
    }

    /**
     * Whether the driver can run automatic encryption on this machine, either
     * with the crypt_shared library or with the mongocryptd process.
     */
    private function hasEncryptionRuntime(): bool
    {
        $library = env('CRYPT_SHARED_LIB_PATH')
            ?? config('database.connections.mongodb.driver_options.autoEncryption.extraOptions.cryptSharedLibPath');

        if (is_string($library) && $library !== '' && is_file($library)) {
            return true;
        }

        $mongocryptd = env('MONGOCRYPTD_PATH');

        if (is_string($mongocryptd) && $mongocryptd !== '' && is_file($mongocryptd)) {
            return true;
        }

        if (! function_exists('exec')) {
            return false;
        }

        exec('command -v mongocryptd', $output, $exitCode);

        return $exitCode === 0;
    }
}
