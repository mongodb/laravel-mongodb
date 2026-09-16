<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Commands;

use Illuminate\Console\Command;
use MongoDB\BSON\Binary;
use MongoDB\BSON\ObjectID;
use MongoDB\Laravel\Connection;
use MongoDB\Laravel\Tests\Models\Patient;
use MongoDB\Laravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;

use function env;

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
