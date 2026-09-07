<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests;

use InvalidArgumentException;
use LogicException;
use MongoDB\Driver\ClientEncryption;
use MongoDB\Laravel\Connection;

use function base64_encode;
use function env;
use function random_bytes;

class ConnectionAutoEncryptionTest extends TestCase
{
    private const KEY_VAULT = 'encryption.__keyVault';

    public function testValidateAutoEncryptionConfigWithoutKeyVaultNamespace(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('keyVaultNamespace');

        new Connection($this->encryptionConfig([
            'kmsProviders' => ['local' => ['key' => base64_encode(random_bytes(96))]],
        ]));
    }

    public function testValidateAutoEncryptionConfigWithoutKmsProviders(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('kmsProviders');

        new Connection($this->encryptionConfig(['keyVaultNamespace' => 'encryption.__keyVault']));
    }

    public function testValidateAutoEncryptionConfigWithInvalidLocalKey(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Connection($this->encryptionConfig([
            'keyVaultNamespace' => 'encryption.__keyVault',
            'kmsProviders' => ['local' => ['key' => 'not-valid-**base64**']],
        ]));
    }

    public function testValidateAutoEncryptionConfigWithShortLocalKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('96 bytes');

        new Connection($this->encryptionConfig([
            'keyVaultNamespace' => 'encryption.__keyVault',
            'kmsProviders' => ['local' => ['key' => base64_encode(random_bytes(32))]],
        ]));
    }

    public function testValidateAutoEncryptionConfigWithEmptyKeyId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('null "keyId"');

        new Connection($this->encryptionConfig([
            'keyVaultNamespace' => 'encryption.__keyVault',
            'kmsProviders' => ['local' => ['key' => base64_encode(random_bytes(96))]],
            'encryptedFieldsMap' => [
                'users' => [
                    'fields' => [
                        ['path' => 'ssn', 'bsonType' => 'string', 'keyId' => null],
                    ],
                ],
            ],
        ]));
    }

    public function testValidateAutoEncryptionConfigValid(): void
    {
        $config = [
            'keyVaultNamespace' => 'encryption.__keyVault',
            'kmsProviders' => ['local' => ['key' => base64_encode(random_bytes(96))]],
            // Explicitly opt out of crypt_shared so this shape validation does
            // not depend on the shared library being present at runtime.
            'extraOptions' => ['cryptSharedLibRequired' => false],
        ];

        $connection = new Connection($this->encryptionConfig($config));

        $this->assertTrue($connection->isEncryptionEnabled($config));
        $this->assertSame($config, $connection->getEncryptionOptions());
    }

    public function testGetClientEncryptionReturned(): void
    {
        $connection = new Connection($this->encryptionConfig([
            'keyVaultNamespace' => 'encryption.__keyVault',
            'kmsProviders' => ['local' => ['key' => base64_encode(random_bytes(96))]],
            'extraOptions' => ['cryptSharedLibRequired' => false],
        ]));

        $this->assertInstanceOf(ClientEncryption::class, $connection->getClientEncryption());
    }

    public function testGetClientEncryptionThrowsWhenNotConfigured(): void
    {
        $connection = new Connection($this->connectionConfig());

        $this->expectException(InvalidArgumentException::class);

        $connection->getClientEncryption();
    }

    public function testNormalizeEncryptedFieldsMapAddsDefaultKeyAltName(): void
    {
        $connection = new Connection($this->connectionConfig());

        $normalized = $connection->normalizeEncryptedFieldsMap([
            'patients' => ['fields' => [['path' => 'ssn', 'bsonType' => 'string']]],
        ]);

        $this->assertSame('patients.ssn', $normalized['patients']['fields'][0]['keyAltName']);
    }

    public function testNormalizeEncryptedFieldsMapPreservesExplicitKeyAltName(): void
    {
        $connection = new Connection($this->connectionConfig());

        $normalized = $connection->normalizeEncryptedFieldsMap([
            'patients' => ['fields' => [['path' => 'ssn', 'bsonType' => 'string', 'keyAltName' => 'my-name']]],
        ]);

        $this->assertSame('my-name', $normalized['patients']['fields'][0]['keyAltName']);
    }

    public function testNormalizeEncryptedFieldsMapAcceptsKeyedSyntax(): void
    {
        $connection = new Connection($this->connectionConfig());

        $normalized = $connection->normalizeEncryptedFieldsMap([
            'patients' => [
                'fields' => [
                    'ssn' => ['bsonType' => 'string', 'queryType' => 'equality'],
                    'billing' => 'object',
                ],
            ],
        ]);

        $byPath = [];
        foreach ($normalized['patients']['fields'] as $field) {
            $byPath[$field['path']] = $field;
        }

        $ssn = $byPath['ssn'];
        $this->assertSame('string', $ssn['bsonType']);
        $this->assertSame('equality', $ssn['queries'][0]['queryType']);
        $this->assertSame('patients.ssn', $ssn['keyAltName']);

        $billing = $byPath['billing'];
        $this->assertSame('object', $billing['bsonType']);
        $this->assertArrayNotHasKey('queries', $billing);
        $this->assertArrayNotHasKey('keyId', $billing);
    }

    public function testNormalizeEncryptedFieldsMapThrowsOnMissingPath(): void
    {
        $this->expectException(LogicException::class);

        $connection = new Connection($this->connectionConfig());
        $connection->normalizeEncryptedFieldsMap([
            'patients' => ['fields' => [['bsonType' => 'string']]],
        ]);
    }

    public function testNormalizeEncryptedFieldsMapThrowsOnMissingBsonType(): void
    {
        $this->expectException(LogicException::class);

        $connection = new Connection($this->connectionConfig());
        $connection->normalizeEncryptedFieldsMap([
            'patients' => ['fields' => [['path' => 'ssn', 'queryType' => 'equality']]],
        ]);
    }

    public function testNormalizeEncryptedFieldsMapThrowsOnBothKeyIdAndKeyAltName(): void
    {
        $this->expectException(LogicException::class);

        $connection = new Connection($this->connectionConfig());
        $connection->normalizeEncryptedFieldsMap([
            'patients' => ['fields' => [['path' => 'ssn', 'bsonType' => 'string', 'keyId' => 'x', 'keyAltName' => 'y']]],
        ]);
    }

    public function testResolveOrCreateEncryptionKeysIsIdempotent(): void
    {
        $connection = new Connection($this->encryptionConfig([
            'keyVaultNamespace' => self::KEY_VAULT,
            'kmsProviders' => ['local' => ['key' => base64_encode(random_bytes(96))]],
            'extraOptions' => ['cryptSharedLibRequired' => false],
        ]));

        $map = static fn (string $altName): array => [
            'patients' => ['fields' => [['path' => 'ssn', 'bsonType' => 'string', 'keyAltName' => $altName]]],
        ];

        $first  = $connection->resolveOrCreateEncryptionKeys($map('tests:idempotent'));
        $second = $connection->resolveOrCreateEncryptionKeys($map('tests:idempotent'));

        $this->assertSame(
            $first['patients']['fields'][0]['keyId']->getData(),
            $second['patients']['fields'][0]['keyId']->getData(),
        );
    }

    public function testResolveOrCreateEncryptionKeysUsesDefaultKeyAltName(): void
    {
        $connection = new Connection($this->encryptionConfig([
            'keyVaultNamespace' => self::KEY_VAULT,
            'kmsProviders' => ['local' => ['key' => base64_encode(random_bytes(96))]],
            'extraOptions' => ['cryptSharedLibRequired' => false],
        ]));

        // The field declares neither a keyId nor a keyAltName.
        $map = ['patients' => ['fields' => [['path' => 'ssn', 'bsonType' => 'string']]]];

        $first  = $connection->resolveOrCreateEncryptionKeys($map);
        $second = $connection->resolveOrCreateEncryptionKeys($map);

        $keyId = $first['patients']['fields'][0]['keyId'];
        $this->assertArrayHasKey('keyId', $first['patients']['fields'][0]);

        // Idempotent: the second call reuses the key minted under "patients.ssn".
        $this->assertSame($keyId->getData(), $second['patients']['fields'][0]['keyId']->getData());

        // The default alternate name used to bind the key.
        $normalized = $connection->normalizeEncryptedFieldsMap($map);
        $this->assertSame('patients.ssn', $normalized['patients']['fields'][0]['keyAltName']);
    }

    /**
     * Build a connection config used by the encryption validation tests.
     *
     * @param  array<string, mixed> $autoEncryption
     *
     * @return array<string, mixed>
     */
    private function encryptionConfig(array $autoEncryption): array
    {
        $config = $this->connectionConfig();

        $config['driver_options'] = ['autoEncryption' => $autoEncryption];

        return $config;
    }

    /**
     * Build a base connection config with no encryption options.
     *
     * @return array<string, mixed>
     */
    private function connectionConfig(): array
    {
        return [
            'name' => 'mongodb',
            'driver' => 'mongodb',
            'dsn' => env('MONGODB_URI', 'mongodb://127.0.0.1/'),
            'database' => env('MONGODB_DATABASE', 'unittest'),
            'options' => [],
        ];
    }
}
