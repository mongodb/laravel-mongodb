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
