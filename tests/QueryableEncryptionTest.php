<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests;

use Carbon\Carbon;
use DateTimeImmutable;
use MongoDB\BSON\Binary;
use MongoDB\BSON\ObjectID;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Laravel\Connection;
use MongoDB\Laravel\Tests\Models\EncryptedCasting;
use MongoDB\Laravel\Tests\Models\EncryptedEmbedded;
use MongoDB\Laravel\Tests\Models\EncryptedUser;
use MongoDB\Laravel\Tests\Models\Patient;
use PHPUnit\Framework\Attributes\Group;

use function config;
use function env;
use function str_starts_with;

/**
 * End-to-end Queryable Encryption integration tests.
 *
 * These run only when the "mongodb" connection is configured with
 * autoEncryption (encryptedFieldsMap) on a MongoDB 8.0+ replica set; the
 * skipIfQEIsNotSupported() guard keeps the community lanes green.
 */
#[Group('queryable-encryption')]
final class QueryableEncryptionTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // Declare the encrypted fields once on the connection. The models
        // carry no encryption metadata: the map is the single source of truth.
        $app['config']->set(
            'database.connections.mongodb.driver_options.autoEncryption',
            $this->encryptionOptions(self::encryptedFieldsMap()),
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Forget any cached connection so the autoEncryption configuration is
        // picked up.
        $this->app->make('db')->purge('mongodb');

        $this->skipIfQEIsNotSupported();

        // Start from a clean database, whatever ran before. Dropping through
        // the Schema builder also drops the metadata collections of the
        // encrypted collections.
        //
        // This has to stay after the guard: PHPUnit still calls tearDown()
        // after a skip, and resolving the connection is what raises when the
        // extension is too old.
        $schema = $this->getConnection('mongodb')->getSchemaBuilder();
        $schema->drop('patients');
        $schema->drop('users');
    }

    public function testEncryptedPatientRoundTrip(): void
    {
        $connection = $this->getConnection('mongodb');
        $connection->getSchemaBuilder()->createEncrypted('patients');

        $patient = Patient::create([
            'ssn' => '123-456-7890',
            'billing_amount' => 1500,
            'billing' => ['credit_card_number' => '0000'],
        ]);

        // The server-managed field is still returned by queries until the
        // follow-up hides it. It is written by the server, so it is not on the
        // in-memory model returned by create().
        $fresh = Patient::findOrFail($patient->getKey());
        $this->assertArrayHasKey('__safeContent__', $fresh->toArray());

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
     * The connection table prefix applies to the encrypted collection, while
     * the encrypted fields map keeps the logical collection names.
     */
    public function testEncryptedCollectionUsesTheTablePrefix(): void
    {
        config(['database.connections.mongodb.prefix' => 'test_']);
        $this->app->make('db')->purge('mongodb');

        $connection = $this->getConnection('mongodb');
        $schema = $connection->getSchemaBuilder();
        $schema->drop('patients');
        $schema->createEncrypted('patients');

        $patient = Patient::create([
            'ssn' => '123-456-7890',
            'billing_amount' => 1500,
            'billing' => ['credit_card_number' => '0000'],
        ]);

        $plain = $this->plainConnection();
        $this->assertTrue($plain->getSchemaBuilder()->hasCollection('test_patients'));

        $raw = $plain->getCollection('test_patients')->findOne(['_id' => new ObjectID($patient->getKey())]);
        $this->assertInstanceOf(Binary::class, $raw['ssn']);

        // The update path resolves the logical name, so the encrypted
        // collection uses a single-document update.
        $patient->update(['billing_amount' => 1600]);
        $this->assertSame(1600, Patient::findOrFail($patient->getKey())->billing_amount);

        $schema->drop('patients');
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

    /**
     * The encrypted fields of the models under test. See
     * resources/boost/skills/laravel-mongodb/references/queryable-encryption.md.
     *
     * @return array<string, mixed>
     */
    private static function encryptedFieldsMap(): array
    {
        return [
            'patients' => [
                'fields' => [
                    'ssn' => ['bsonType' => 'string', 'queryType' => 'equality'],
                    'billing_amount' => ['bsonType' => 'int', 'queryType' => 'range', 'min' => 0, 'max' => 5000, 'sparsity' => 1],
                    'billing' => 'object',
                ],
            ],
            'users' => [
                'fields' => [
                    'name' => 'string',
                    'email' => ['bsonType' => 'string', 'queryType' => 'equality'],
                    'phone' => ['bsonType' => 'string', 'queryType' => 'equality'],
                    'date_of_birth' => [
                        'bsonType' => 'date',
                        'queryType' => 'range',
                        'min' => new UTCDateTime(new DateTimeImmutable('1900-01-01')),
                        'max' => new UTCDateTime(new DateTimeImmutable('2100-01-01')),
                        'sparsity' => 1,
                    ],
                ],
            ],
            'encrypted_casting' => [
                'fields' => [
                    'an_int' => ['bsonType' => 'int', 'queryType' => 'range', 'min' => 0, 'max' => 10000, 'sparsity' => 1],
                    'a_long' => 'long',
                    'a_double' => 'double',
                    'a_decimal' => 'decimal',
                    'a_bool' => 'bool',
                    'a_date' => 'date',
                    'an_object' => 'object',
                    'a_string' => 'string',
                    'billing.amount' => ['bsonType' => 'int', 'queryType' => 'range', 'min' => 0, 'max' => 10000, 'sparsity' => 1],
                    'billing.label' => 'string',
                ],
            ],
            'encrypted_embedded' => [
                'fields' => [
                    'name' => 'string',
                    'address.amount' => ['bsonType' => 'int', 'queryType' => 'range', 'min' => 0, 'max' => 10000, 'sparsity' => 1],
                    'address.label.amount' => 'int',
                ],
            ],
        ];
    }

    /**
     * Values that come from a form or a JSON API are strings. The package
     * derives a cast from each bsonType and coerces the value on write, so
     * automatic encryption accepts it instead of rejecting a string.
     */
    public function testEncryptedFieldsAreCoercedToNativeBsonTypes(): void
    {
        $connection = $this->getConnection('mongodb');
        $schema = $connection->getSchemaBuilder();
        $schema->drop('encrypted_casting');
        $schema->createEncrypted('encrypted_casting');

        $model = EncryptedCasting::create([
            'an_int' => '1500',
            'a_long' => '1500',
            'a_double' => '15.5',
            'a_decimal' => '1500.25',
            'a_bool' => '1',
            'a_date' => '2020-01-02 15:30:00',
            'an_object' => ['foo' => 'bar'],
            'a_string' => 'hello',
            'billing' => ['amount' => '1500', 'label' => 'invoice'],
        ]);

        $fresh = EncryptedCasting::findOrFail($model->getKey());

        self::assertSame(1500, $fresh->an_int);
        self::assertSame(1500, $fresh->a_long);
        self::assertSame(15.5, $fresh->a_double);
        self::assertSame('1500.25', (string) $fresh->a_decimal);
        self::assertTrue($fresh->a_bool);
        // The datetime cast keeps the time of the stored timestamp.
        self::assertSame('2020-01-02 15:30:00', $fresh->a_date->format('Y-m-d H:i:s'));
        self::assertSame('hello', $fresh->a_string);
        self::assertSame(['foo' => 'bar'], $fresh->an_object);
        self::assertSame(['amount' => 1500, 'label' => 'invoice'], $fresh->billing);

        // The range query only matches if the value is stored as an int.
        self::assertSame(1, EncryptedCasting::whereBetween('an_int', [1000, 2000])->count());
        self::assertSame(1, EncryptedCasting::whereBetween('billing.amount', [1000, 2000])->count());

        // Encryption at rest: the native type is hidden by the ciphertext.
        $raw = $this->plainConnection()->getCollection('encrypted_casting')->findOne(['_id' => new ObjectID($model->getKey())]);
        self::assertInstanceOf(Binary::class, $raw['an_int']);
        self::assertInstanceOf(Binary::class, $raw['a_long']);
        self::assertInstanceOf(Binary::class, $raw['billing']['amount']);

        $schema->drop('encrypted_casting');
    }

    /**
     * An embedded relation stores the embedded document inside the document of
     * the parent, so its encrypted fields are declared as dotted paths in the
     * map of the parent collection and are coerced when the embedded model is
     * written through its relation.
     */
    public function testEncryptedFieldsOfEmbeddedRelationsAreCoerced(): void
    {
        $connection = $this->getConnection('mongodb');
        $schema = $connection->getSchemaBuilder();
        $schema->drop('encrypted_embedded');
        $schema->createEncrypted('encrypted_embedded');

        $parent = EncryptedEmbedded::create(['name' => 'Jane']);
        $parent->address()->create(['amount' => '1500', 'label' => ['amount' => '1600']]);

        $fresh = EncryptedEmbedded::findOrFail($parent->getKey());

        self::assertSame(1500, $fresh->address->amount);
        self::assertSame(1600, $fresh->address->label['amount']);

        // The range query only matches if the value is stored as an int.
        self::assertSame(1, EncryptedEmbedded::whereBetween('address.amount', [1000, 2000])->count());

        // Encryption at rest: the native type is hidden by the ciphertext.
        $raw = $this->plainConnection()->getCollection('encrypted_embedded')->findOne(['_id' => new ObjectID($parent->getKey())]);
        self::assertInstanceOf(Binary::class, $raw['address']['amount']);
        self::assertInstanceOf(Binary::class, $raw['address']['label']['amount']);

        $schema->drop('encrypted_embedded');
    }
}
