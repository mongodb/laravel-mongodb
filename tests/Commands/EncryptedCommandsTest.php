<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Tests\Commands;

use Illuminate\Console\Command;
use MongoDB\Laravel\Tests\TestCase;

use function base64_encode;
use function config;
use function random_bytes;

class EncryptedCommandsTest extends TestCase
{
    public function testDiagnoseFailsWhenEncryptionNotConfigured(): void
    {
        $this->artisan('mongodb:encrypted:diagnose', ['--no-server' => true])
            ->expectsOutputToContain('Queryable Encryption is not enabled')
            ->assertExitCode(Command::FAILURE);
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

    /**
     * Configure automatic encryption on the default MongoDB connection.
     *
     * @param  array<string, mixed> $encryptedFieldsMap
     */
    private function enableEncryption(array $encryptedFieldsMap): void
    {
        config([
            'database.connections.mongodb.driver_options.autoEncryption' => [
                'keyVaultNamespace' => 'encryption.__keyVault',
                'kmsProviders' => ['local' => ['key' => base64_encode(random_bytes(96))]],
                // Opt out of crypt_shared so the tests run on a community server.
                'extraOptions' => ['cryptSharedLibRequired' => false],
                'encryptedFieldsMap' => $encryptedFieldsMap,
            ],
        ]);
    }
}
