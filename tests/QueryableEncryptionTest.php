<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests;

use Carbon\Carbon;
use MongoDB\BSON\Binary;
use MongoDB\BSON\ObjectID;
use MongoDB\Laravel\Connection;
use MongoDB\Laravel\Tests\Models\EncryptedUser;
use MongoDB\Laravel\Tests\Models\Patient;

use function env;
use function str_starts_with;

/**
 * End-to-end Queryable Encryption integration tests.
 *
 * These run only when the "mongodb" connection is configured with
 * autoEncryption (encryptedFieldsMap) on a MongoDB 8.0+ replica set; the
 * skipIfQEIsNotSupported() guard keeps the community lanes green.
 */
final class QueryableEncryptionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->skipIfQEIsNotSupported();
    }

    protected function tearDown(): void
    {
        $connection = $this->getConnection('mongodb');

        if ($connection->getSchemaBuilder()->hasCollection('patients')) {
            $connection->getDatabase()->dropCollection('patients');
        }

        if ($connection->getSchemaBuilder()->hasCollection('users')) {
            $connection->getDatabase()->dropCollection('users');
        }

        parent::tearDown();
    }

    public function testEncryptedPatientRoundTrip(): void
    {
        $connection = $this->getConnection('mongodb');
        $connection->getDatabase()->dropCollection('patients');
        $connection->getSchemaBuilder()->createEncrypted('patients');

        $patient = Patient::create([
            'ssn' => '123-456-7890',
            'billing_amount' => 1500,
            'billing' => ['credit_card_number' => '0000'],
        ]);

        // The server-managed field is never serialized.
        $this->assertArrayNotHasKey('__safeContent__', $patient->toArray());

        // Equality and range queries on encrypted fields.
        $this->assertTrue(Patient::where('ssn', '123-456-7890')->exists());
        $this->assertSame(1, Patient::whereBetween('billing_amount', [0, 2000])->count());

        // Encryption at rest: plaintext is not visible to a plain connection.
        $raw = $this->plainConnection()->getCollection('patients')->findOne(['_id' => new ObjectID($patient->getKey())]);
        $this->assertInstanceOf(Binary::class, $raw['ssn']);
        $this->assertInstanceOf(Binary::class, $raw['billing_amount']);
        $this->assertArrayHasKey('__safeContent__', (array) $raw);
    }

    public function testEncryptedUserRoundTrip(): void
    {
        $connection = $this->getConnection('mongodb');
        $connection->getDatabase()->dropCollection('users');
        $connection->getSchemaBuilder()->createEncrypted('users');

        $user = EncryptedUser::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+1-555-0100',
            'date_of_birth' => '1990-05-15',
            'password' => 'secret123',
        ]);

        // The password stays hashed, not encrypted.
        $this->assertNotFalse(str_starts_with($user->password, '$2'));

        // Equality queries (email, phone) and range query (date of birth).
        $this->assertTrue(EncryptedUser::where('email', 'jane@example.com')->exists());
        $this->assertTrue(EncryptedUser::where('phone', '+1-555-0100')->exists());
        $this->assertSame(1, EncryptedUser::whereBetween('date_of_birth', [Carbon::create(1980, 1, 1), Carbon::create(2000, 1, 1)])->count());

        // Encryption at rest.
        $raw = $this->plainConnection()->getCollection('users')->findOne(['_id' => new ObjectID($user->getKey())]);
        $this->assertInstanceOf(Binary::class, $raw['email']);
        $this->assertInstanceOf(Binary::class, $raw['phone']);
        $this->assertIsString($raw['password']);
        $this->assertArrayHasKey('__safeContent__', (array) $raw);
    }

    /**
     * A plain connection to the same server, free of auto encryption, used to
     * inspect the ciphertext stored by the auto-encrypted connection.
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
