<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Commands;

use Illuminate\Console\Command;
use MongoDB\BSON\Binary;
use MongoDB\BSON\ObjectID;
use MongoDB\Laravel\Connection;
use MongoDB\Laravel\Tests\Models\Patient;
use MongoDB\Laravel\Tests\TestCase;

use function base64_encode;
use function config;
use function env;
use function random_bytes;

class EncryptedCommandsTest extends TestCase
{
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

    public function testDiagnoseNoServerListsMappedCollections(): void
    {
        $this->enableEncryption(['users' => ['fields' => []]]);

        $this->artisan('mongodb:encrypted:diagnose', ['--no-server' => true])
            ->expectsOutputToContain('configuration is valid')
            ->expectsOutputToContain('users')
            ->assertExitCode(Command::SUCCESS);
    }

    public function testCreateNoServerValidatesConfiguration(): void
    {
        $this->enableEncryption(['users' => ['fields' => []]]);

        $this->artisan('mongodb:encrypted:create', ['collection' => 'users', '--no-server' => true])
            ->expectsOutputToContain('would be created')
            ->assertExitCode(Command::SUCCESS);
    }

    public function testCreateNoServerFailsWithoutMappedCollection(): void
    {
        $this->enableEncryption([]);

        $this->artisan('mongodb:encrypted:create', ['collection' => 'users', '--no-server' => true])
            ->expectsOutputToContain('No "encryptedFieldsMap[users]" entry')
            ->assertExitCode(Command::FAILURE);
    }

    public function testCreateCreatesAnEncryptedCollection(): void
    {
        $this->enableEncryption(self::PATIENTS_MAP);
        $this->purgeMongodb();
        $this->skipIfQEIsNotSupported();

        $plain = $this->plainConnection();
        $plain->getDatabase()->dropCollection('patients');

        $this->artisan('mongodb:encrypted:create', ['collection' => 'patients'])
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

    public function testCreateIsIdempotent(): void
    {
        $this->enableEncryption(self::PATIENTS_MAP);
        $this->purgeMongodb();
        $this->skipIfQEIsNotSupported();

        $plain = $this->plainConnection();
        $plain->getDatabase()->dropCollection('patients');

        $this->artisan('mongodb:encrypted:create', ['collection' => 'patients'])->assertExitCode(Command::SUCCESS);
        $this->artisan('mongodb:encrypted:create', ['collection' => 'patients'])
            ->expectsOutputToContain('Created encrypted collection "patients"')
            ->assertExitCode(Command::SUCCESS);
    }

    public function testDiagnoseListsMappedCollections(): void
    {
        $this->enableEncryption(self::PATIENTS_MAP);
        $this->purgeMongodb();
        $this->skipIfQEIsNotSupported();

        $this->artisan('mongodb:encrypted:diagnose')
            ->expectsOutputToContain('configuration is valid')
            ->expectsOutputToContain('patients')
            ->assertExitCode(Command::SUCCESS);
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

    /**
     * @param  array<string, mixed> $encryptedFieldsMap
     *
     * @return array<string, mixed>
     */
    private function encryptionOptions(array $encryptedFieldsMap): array
    {
        return [
            'keyVaultNamespace' => 'encryption.__keyVault',
            'kmsProviders' => ['local' => ['key' => base64_encode(random_bytes(96))]],
            // Opt out of crypt_shared so the tests run on a community server.
            'extraOptions' => ['cryptSharedLibRequired' => false],
            'encryptedFieldsMap' => $encryptedFieldsMap,
        ];
    }

    /**
     * Override the encrypted fields map on the default MongoDB connection.
     *
     * @param  array<string, mixed> $encryptedFieldsMap
     */
    private function enableEncryption(array $encryptedFieldsMap): void
    {
        config([
            'database.connections.mongodb.driver_options.autoEncryption' => $this->encryptionOptions($encryptedFieldsMap),
        ]);
    }
}
