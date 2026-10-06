<?php

declare(strict_types=1);

/**
 * Resolve the Automatic Encryption Shared Library (crypt_shared) release to
 * install for the Queryable Encryption tests.
 *
 * The MongoDB download manifest is read once, and the latest production
 * release of the requested major version is selected. The version, the
 * download URL and its sha256 are printed as GitHub Actions step outputs, so a
 * workflow reads the manifest a single time:
 *
 *     php .github/scripts/crypt_shared_version.php --target ubuntu2404 >> "$GITHUB_OUTPUT"
 *
 * The target distribution must match the runner image, crypt_shared being a
 * client-side library.
 *
 * Usage: php crypt_shared_version.php [--major=8.0] [--target=ubuntu2404]
 */

// current.json holds one release per major series and is around 460 KB, where
// full.json lists every release and is around 50 MB. Both carry the
// crypt_shared downloads; pass --manifest to read another one.
const MANIFEST_URL = 'https://downloads.mongodb.org/current.json';
const ARCHITECTURE = 'x86_64';
const EDITION = 'enterprise';

/**
 * @return list<array<string, mixed>>
 */
function readManifest(string $url): array
{
    $manifest = file_get_contents($url);
    if ($manifest === false) {
        throw new RuntimeException(sprintf('Unable to read the download manifest at %s.', $url));
    }

    return json_decode($manifest, true, flags: JSON_THROW_ON_ERROR)['versions'] ?? [];
}

/**
 * The latest production release of a major version, by descending version.
 *
 * @param  list<array<string, mixed>> $versions
 *
 * @return array<string, mixed>
 */
function latestRelease(array $versions, string $major): array
{
    $candidates = [];

    foreach ($versions as $release) {
        $version = $release['version'] ?? null;

        if (! is_string($version) || ! str_starts_with($version, $major . '.') || empty($release['production_release'])) {
            continue;
        }

        $candidates[$version] = $release;
    }

    if ($candidates === []) {
        throw new RuntimeException(sprintf('No production release found for MongoDB %s.', $major));
    }

    // version_compare so that 8.0.32 sorts above 8.0.9.
    uksort($candidates, static fn (string $a, string $b): int => version_compare($b, $a));

    return $candidates[array_key_first($candidates)];
}

/**
 * @param  array<string, mixed> $release
 *
 * @return array{url: string, sha256: string}
 */
function cryptSharedDownload(array $release, string $target): array
{
    foreach ($release['downloads'] ?? [] as $download) {
        if (($download['target'] ?? null) !== $target || ($download['arch'] ?? null) !== ARCHITECTURE || ($download['edition'] ?? null) !== EDITION) {
            continue;
        }

        $cryptShared = $download['crypt_shared'] ?? null;

        if (! is_array($cryptShared) || ! is_string($cryptShared['url'] ?? null) || ! is_string($cryptShared['sha256'] ?? null)) {
            continue;
        }

        return ['url' => $cryptShared['url'], 'sha256' => $cryptShared['sha256']];
    }

    throw new RuntimeException(sprintf('No crypt_shared archive published for target "%s" and architecture "%s" in MongoDB %s.', $target, ARCHITECTURE, $release['version']));
}

try {
    $options = getopt('', ['manifest:', 'major:', 'target:']);

    $manifestUrl = is_string($options['manifest'] ?? null) ? $options['manifest'] : MANIFEST_URL;
    $major = is_string($options['major'] ?? null) ? $options['major'] : '8.0';
    $target = is_string($options['target'] ?? null) ? $options['target'] : 'ubuntu2404';

    $release = latestRelease(readManifest($manifestUrl), $major);
    $download = cryptSharedDownload($release, $target);

    echo 'version=', $release['version'], PHP_EOL;
    echo 'url=', $download['url'], PHP_EOL;
    echo 'sha256=', $download['sha256'], PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);

    exit(1);
}
