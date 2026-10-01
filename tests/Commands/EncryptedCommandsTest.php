<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Commands;

use Illuminate\Console\Command;
use MongoDB\BSON\Binary;
use MongoDB\BSON\ObjectID;
use MongoDB\Laravel\Connection;
use MongoDB\Laravel\Tests\Models\Patient;
use MongoDB\Laravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

use function base64_encode;
use function config;
use function count;
use function env;
use function sprintf;
use function str_pad;

class EncryptedCommandsTest extends TestCase
{
    private const KEY_VAULT_DATABASE = 'encryption';

    // The key vault shared with the other Queryable Encryption tests. They all
    // wrap their data keys under the same local master key, so rewrapping every
    // key in it keeps them readable.
    private const KEY_VAULT_COLLECTION = '__keyVault';

    private const KEY_VAULT_NAMESPACE = self::KEY_VAULT_DATABASE . '.' . self::KEY_VAULT_COLLECTION;

    // Rotating to "local:rotated" leaves the data keys wrapped under a provider
    // nothing else can unwrap, so that test owns a separate key vault.
    private const ROTATED_KEY_VAULT_NAMESPACE = self::KEY_VAULT_DATABASE . '.__commandsRotatedKeyVault';

    private const PATIENTS_MAP = [
        'patients' => [
            'fields' => [
                ['path' => 'ssn', 'bsonType' => 'string', 'queries' => [['queryType' => 'equality']]],
            ],
        ],
    ];

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // Configure automatic encryption so the encryption commands are
        // registered on the "mongodb" connection (registered in boot());
        // the config is fully loaded by then.
        $app['config']->set('database.connections.mongodb.driver_options.autoEncryption', $this->encryptionOptions([]));
    }

    public function testStatusNoServerListsMappedCollections(): void
    {
        $this->enableEncryption(['users' => ['fields' => []]]);

        $this->artisan('mongodb:encryption:status', ['--no-server' => true])
            ->expectsOutputToContain('configuration is valid')
            ->expectsOutputToContain('users')
            ->assertExitCode(Command::SUCCESS);
    }

    public function testCreateNoServerValidatesConfiguration(): void
    {
        $this->enableEncryption(['users' => ['fields' => []]]);

        $this->artisan('mongodb:encryption:create-collection', ['collection' => 'users', '--no-server' => true])
            ->expectsOutputToContain('would be created')
            ->assertExitCode(Command::SUCCESS);
    }

    public function testCreateNoServerFailsWithoutMappedCollection(): void
    {
        $this->enableEncryption([]);

        $this->artisan('mongodb:encryption:create-collection', ['collection' => 'users', '--no-server' => true])
            ->expectsOutputToContain('No "encryptedFieldsMap[users]" entry')
            ->assertExitCode(Command::FAILURE);
    }

    #[Group('queryable-encryption')]
    public function testCreateCreatesAnEncryptedCollection(): void
    {
        $this->enableEncryption(self::PATIENTS_MAP);
        $this->skipIfQEIsNotSupported();

        $plain = $this->plainConnection();
        $this->dropEncryptedCollection('patients');

        $this->artisan('mongodb:encryption:create-collection', ['collection' => 'patients'])
            ->expectsOutputToContain('Created encrypted collection "patients"')
            ->assertExitCode(Command::SUCCESS);

        // The collection is registered server-side as encrypted.
        $encrypted = [];
        foreach ($plain->getDatabase()->listCollections(['filter' => ['options.encryptedFields' => ['$exists' => true]]]) as $info) {
            $encrypted[] = $info->getName();
        }

        $this->assertContains('patients', $encrypted);

        // Writes are enciphered at rest when viewed with a plain connection.
        $patient = Patient::create(['ssn' => '123-45-6789', 'billing_amount' => 1500, 'billing' => ['credit_card_number' => '0000']]);
        $raw = $plain->getCollection('patients')->findOne(['_id' => new ObjectID($patient->getKey())]);
        $this->assertInstanceOf(Binary::class, $raw['ssn']);
    }

    #[Group('queryable-encryption')]
    public function testCreateIsIdempotent(): void
    {
        $this->enableEncryption(self::PATIENTS_MAP);
        $this->skipIfQEIsNotSupported();

        $this->dropEncryptedCollection('patients');

        $this->artisan('mongodb:encryption:create-collection', ['collection' => 'patients'])->assertExitCode(Command::SUCCESS);
        $this->artisan('mongodb:encryption:create-collection', ['collection' => 'patients'])
            ->expectsOutputToContain('Created encrypted collection "patients"')
            ->assertExitCode(Command::SUCCESS);
    }

    #[Group('queryable-encryption')]
    public function testCreateAfterDropRecreatesTheCollection(): void
    {
        $this->enableEncryption(self::PATIENTS_MAP);
        $this->skipIfQEIsNotSupported();

        $connection = $this->getConnection('mongodb');
        $connection->getSchemaBuilder()->createEncrypted('patients');
        $connection->getSchemaBuilder()->drop('patients');

        // The metadata collections were dropped along with the collection, so
        // the collection can be created again.
        $this->artisan('mongodb:encryption:create-collection', ['collection' => 'patients'])
            ->expectsOutputToContain('Created encrypted collection "patients"')
            ->assertExitCode(Command::SUCCESS);
    }

    #[Group('queryable-encryption')]
    public function testStatusListsMappedCollections(): void
    {
        $this->enableEncryption(self::PATIENTS_MAP);
        $this->skipIfQEIsNotSupported();

        $this->artisan('mongodb:encryption:status')
            ->expectsOutputToContain('configuration is valid')
            ->expectsOutputToContain('patients')
            ->assertExitCode(Command::SUCCESS);
    }

    public function testRewrapDataKeysFailsWithoutEncryptionConfigured(): void
    {
        config(['database.connections.mongodb.driver_options' => []]);
        $this->purgeMongodb();

        $this->artisan('mongodb:encryption:rewrap-data-keys', ['--force' => true])
            ->expectsOutputToContain('Queryable Encryption is not enabled')
            ->assertExitCode(Command::FAILURE);
    }

    public function testRewrapDataKeysRejectsInvalidFilterJson(): void
    {
        // An empty map: the options are parsed before any encryption work, and
        // a mapped field would need a keyAltName, which old libmongocrypt
        // versions reject.
        $this->enableEncryption([]);

        $this->artisan('mongodb:encryption:rewrap-data-keys', ['--filter' => 'not json', '--force' => true])
            ->expectsOutputToContain('The "--filter" option is not valid JSON')
            ->assertExitCode(Command::FAILURE);
    }

    public function testRewrapDataKeysRejectsNonObjectMasterKey(): void
    {
        $this->enableEncryption([]);

        $this->artisan('mongodb:encryption:rewrap-data-keys', ['--master-key' => '"a string"', '--force' => true])
            ->expectsOutputToContain('The "--master-key" option must be a JSON object')
            ->assertExitCode(Command::FAILURE);
    }

    /** @return array<string, array{string, string}> */
    public static function provideJsonArrayOptions(): array
    {
        return [
            'filter list' => ['--filter', '[1]'],
            'filter empty array' => ['--filter', '[]'],
            'master key empty array' => ['--master-key', '[]'],
        ];
    }

    /** A JSON array decodes to a PHP array, so it must be rejected explicitly. */
    #[DataProvider('provideJsonArrayOptions')]
    public function testRewrapDataKeysRejectsJsonArrayOptions(string $option, string $value): void
    {
        $this->enableEncryption([]);

        $this->artisan('mongodb:encryption:rewrap-data-keys', [$option => $value, '--force' => true])
            ->expectsOutputToContain(sprintf('The "%s" option must be a JSON object', $option))
            ->assertExitCode(Command::FAILURE);
    }

    public function testRewrapDataKeysRejectsUnconfiguredProvider(): void
    {
        $this->enableEncryption([]);

        $this->artisan('mongodb:encryption:rewrap-data-keys', ['--provider' => 'aws', '--force' => true])
            ->expectsOutputToContain('The "aws" KMS provider is not configured. Configured providers: "local".')
            ->assertExitCode(Command::FAILURE);
    }

    #[Group('queryable-encryption')]
    public function testRewrapDataKeysRewrapsKeysUnderTheConfiguredProvider(): void
    {
        $this->enableEncryption(self::PATIENTS_MAP);
        $this->skipIfQEIsNotSupported();

        $this->recreateEncryptedPatients();

        $before = $this->dataKeys();
        $this->assertNotEmpty($before, 'Creating the encrypted collection should generate a data key.');

        $this->artisan('mongodb:encryption:rewrap-data-keys', ['--force' => true])
            ->expectsOutputToContain(sprintf('Rewrapped %d data key(s) in "%s"', count($before), self::KEY_VAULT_NAMESPACE))
            ->assertExitCode(Command::SUCCESS);

        // The data keys keep their identity and creation date; only the master
        // key wrapping them changes, which is why the documents stay readable.
        foreach ($this->dataKeys() as $id => $after) {
            $this->assertArrayHasKey($id, $before);
            $this->assertNotEquals($before[$id]->keyMaterial, $after->keyMaterial);
            $this->assertEquals($before[$id]->creationDate, $after->creationDate);
            $this->assertGreaterThan($before[$id]->updateDate, $after->updateDate);
        }
    }

    #[Group('queryable-encryption')]
    public function testRewrapDataKeysKeepsExistingDocumentsReadable(): void
    {
        $this->enableEncryption(self::PATIENTS_MAP);
        $this->skipIfQEIsNotSupported();

        $this->recreateEncryptedPatients();
        $patient = Patient::create(['ssn' => '123-45-6789']);

        $this->artisan('mongodb:encryption:rewrap-data-keys', ['--force' => true])->assertExitCode(Command::SUCCESS);
        $this->purgeMongodb();

        $this->assertSame('123-45-6789', Patient::query()->find($patient->getKey())->ssn);
    }

    #[Group('queryable-encryption')]
    public function testRewrapDataKeysRotatesToAnotherNamedLocalProvider(): void
    {
        // Rotating a local master key needs the old and the new key present at
        // once, so the DEKs can be unwrapped before being rewrapped. Named KMS
        // providers are the only way to configure two local keys.
        //
        // The rewrap leaves the data keys wrapped under "local:rotated", which
        // the other tests cannot unwrap, so this one owns a separate key vault.
        $options = $this->encryptionOptions(self::PATIENTS_MAP);
        $options['keyVaultNamespace'] = self::ROTATED_KEY_VAULT_NAMESPACE;
        $options['kmsProviders'] = [
            'local' => ['key' => self::localMasterKey('local')],
            'local:rotated' => ['key' => self::localMasterKey('local:rotated')],
        ];
        config(['database.connections.mongodb.driver_options.autoEncryption' => $options]);
        $this->purgeMongodb();
        $this->skipIfQEIsNotSupported();

        $this->recreateEncryptedPatients();
        $patient = Patient::create(['ssn' => '123-45-6789']);

        $this->artisan('mongodb:encryption:rewrap-data-keys', ['--provider' => 'local:rotated', '--force' => true])
            ->expectsOutputToContain('local:rotated')
            ->assertExitCode(Command::SUCCESS);
        $this->purgeMongodb();

        $this->assertSame('123-45-6789', Patient::query()->find($patient->getKey())->ssn);
    }

    #[Group('queryable-encryption')]
    public function testRewrapDataKeysWarnsWhenNoKeyMatchesTheFilter(): void
    {
        $this->enableEncryption(self::PATIENTS_MAP);
        $this->skipIfQEIsNotSupported();

        $this->artisan('mongodb:encryption:rewrap-data-keys', [
            '--filter' => '{"keyAltNames":"does-not-exist"}',
            '--force' => true,
        ])
            ->expectsOutputToContain('matched the filter')
            ->assertExitCode(Command::SUCCESS);
    }

    /**
     * Create the encrypted "patients" collection from scratch. An existing
     * collection is bound to the data key it was created with, so it is dropped
     * first: the tests do not share one key vault, and the server rejects a
     * recreation with a different key.
     */
    private function recreateEncryptedPatients(): void
    {
        $this->dropEncryptedCollection('patients');

        $this->artisan('mongodb:encryption:create-collection', ['collection' => 'patients'])->assertExitCode(Command::SUCCESS);
    }

    /**
     * The data keys of the key vault, read through a plain connection and keyed
     * by their base64 id so they can be compared across a rewrap.
     *
     * @return array<string, object>
     */
    private function dataKeys(): array
    {
        $keys = [];

        $vault = $this->plainConnection()->getDatabase(self::KEY_VAULT_DATABASE)->selectCollection(self::KEY_VAULT_COLLECTION);

        foreach ($vault->find() as $key) {
            $keys[base64_encode($key->_id->getData())] = $key;
        }

        return $keys;
    }

    /**
     * A fixed 96-byte local master key derived from a name, so the key vault
     * survives a test run. Used by the rotation test, which needs two distinct
     * local keys at once.
     */
    private static function localMasterKey(string $name): string
    {
        return base64_encode(str_pad($name, 96, '0'));
    }

    /**
     * Forget any cached "mongodb" connection so the freshly set
     * autoEncryption map is picked up on the next access.
     */
    private function purgeMongodb(): void
    {
        $this->app->make('db')->purge('mongodb');
    }

    /**
     * Drop the encrypted collection and its metadata collections, so the next
     * creation starts from a clean database.
     */
    private function dropEncryptedCollection(string $collection): void
    {
        $this->getConnection('mongodb')->getSchemaBuilder()->drop($collection);
    }

    /**
     * A plain connection to the same server, free of auto encryption, used to
     * inspect the encrypted collection registered server-side.
     */
    private function plainConnection(): Connection
    {
        return new Connection([
            'name' => 'mongodb_plain',
            'driver' => 'mongodb',
            'dsn' => env('MONGODB_URI', 'mongodb://127.0.0.1/'),
            'database' => env('MONGODB_DATABASE', 'unittest'),
            'options' => [],
        ]);
    }
}
