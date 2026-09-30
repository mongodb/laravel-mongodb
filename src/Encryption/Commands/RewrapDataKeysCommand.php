<?php

declare(strict_types=1);

namespace MongoDB\Laravel\Encryption\Commands;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use JsonException;
use stdClass;

use function array_key_first;
use function blank;
use function data_get;
use function is_array;
use function json_decode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * Re-encrypt the data keys of the key vault under a new master key.
 */
final class RewrapDataKeysCommand extends Command
{
    use ConfirmableTrait;
    use InteractsWithEncryption;

    protected $signature = 'mongodb:encryption:rewrap-data-keys
        {--provider= : The KMS provider to wrap the data keys under (local, aws, azure, gcp, kmip)}
        {--master-key= : The new master key as JSON, required for cloud KMS providers}
        {--filter= : A JSON key vault query narrowing which data keys are rewrapped}
        {--connection= : The MongoDB connection to use}
        {--force : Force the operation to run when in production}';

    protected $description = 'Re-encrypt the data keys under a new master key from a KMS provider.';

    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        try {
            $connection = $this->connection();
            $config = $this->autoEncryptionConfig($connection);
            $filter = $this->jsonOption('filter') ?? [];
            $options = $this->rewrapOptions($config);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $result = $connection->getClientEncryption()->rewrapManyDataKey($filter, $options);

        $this->report($result, $this->keyVaultNamespace($config), $options['provider']);

        return self::SUCCESS;
    }

    /**
     * The options for the rewrap, defaulting to the connection configuration so
     * that a rotation can be driven from config/database.php alone.
     *
     * A master key is omitted unless one is given: the driver rejects a null
     * master key, and a local provider has none.
     *
     * @param  array<string, mixed> $config
     *
     * @return array{provider: string, masterKey?: array<string, mixed>}
     */
    private function rewrapOptions(array $config): array
    {
        $options = ['provider' => $this->provider($config)];
        $masterKey = $this->jsonOption('master-key') ?? Arr::get($config, 'masterKey');

        if (is_array($masterKey)) {
            $options['masterKey'] = $masterKey;
        }

        return $options;
    }

    /**
     * The KMS provider to wrap the data keys under, falling back to the first
     * one configured on the connection.
     *
     * @param  array<string, mixed> $config
     */
    private function provider(array $config): string
    {
        $provider = $this->option('provider')
            ?: array_key_first(Arr::wrap(Arr::get($config, 'kmsProviders')));

        if (blank($provider)) {
            throw new InvalidArgumentException('No KMS provider to rewrap the data keys under. Pass "--provider" or configure "driver_options.autoEncryption.kmsProviders".');
        }

        return (string) $provider;
    }

    /**
     * Decode a JSON command option, or null when it was not given.
     *
     * Decoded as an object rather than associatively, so that a JSON array is
     * rejected: both `{}` and `[]` decode to a PHP array, and the driver
     * expects a document for a filter and a master key alike.
     *
     * @return array<string, mixed>|null
     *
     * @throws InvalidArgumentException When the option is not a JSON object.
     */
    private function jsonOption(string $name): ?array
    {
        $json = $this->option($name);

        if (blank($json)) {
            return null;
        }

        try {
            $decoded = json_decode($json, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException(sprintf('The "--%s" option is not valid JSON: %s', $name, $e->getMessage()), previous: $e);
        }

        if (! $decoded instanceof stdClass) {
            throw new InvalidArgumentException(sprintf('The "--%s" option must be a JSON object.', $name));
        }

        return (array) $decoded;
    }

    /** @param array<string, mixed> $config */
    private function keyVaultNamespace(array $config): string
    {
        return (string) Arr::get($config, 'keyVaultNamespace', 'the key vault');
    }

    private function report(object $result, string $namespace, string $provider): void
    {
        $modified = (int) data_get($result, 'bulkWriteResult.nModified', 0);

        if ($modified === 0) {
            $this->warn(sprintf('No data key in "%s" matched the filter. Nothing was rewrapped.', $namespace));

            return;
        }

        $this->info(sprintf('Rewrapped %d data key(s) in "%s" under the "%s" master key.', $modified, $namespace, $provider));
    }
}
