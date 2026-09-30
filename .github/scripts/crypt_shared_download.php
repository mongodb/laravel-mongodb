<?php

declare(strict_types=1);

/**
 * Install the Automatic Encryption Shared Library (crypt_shared) used by the
 * Queryable Encryption tests.
 *
 * The archive is downloaded, checked against the expected sha256, extracted,
 * and the path to the shared library is printed on stdout. Everything else
 * goes to stderr, so the output can be captured directly:
 *
 *     CRYPT_SHARED_LIB_PATH=$(php .github/scripts/crypt_shared_download.php --url=... --sha256=...)
 *
 * When a library is already extracted under --directory, which is the case
 * when the CI restored it from its cache, it is reused and nothing is
 * downloaded. The cache key carries the version, so a restored cache always
 * holds the release the caller resolved.
 *
 * Usage: php crypt_shared_download.php --url=URL --sha256=HASH [--directory=/tmp/crypt_shared]
 */

function downloadArchive(string $url, string $destination): void
{
    $archive = file_get_contents($url);
    if ($archive === false) {
        throw new RuntimeException(sprintf('Unable to download "%s".', $url));
    }

    if (file_put_contents($destination, $archive) === false) {
        throw new RuntimeException(sprintf('Unable to write "%s".', $destination));
    }
}

function extractArchive(string $archive, string $directory): void
{
    (new PharData($archive))->extractTo($directory, null, true);
}

function findLibrary(string $directory): ?string
{
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if ($file->getFilename() === 'mongo_crypt_v1.so') {
            return $file->getPathname();
        }
    }

    return null;
}

try {
    $options = getopt('', ['url:', 'sha256:', 'directory:']);

    $url = $options['url'] ?? null;
    $sha256 = $options['sha256'] ?? null;

    if (! is_string($url) || ! is_string($sha256)) {
        throw new RuntimeException('The --url and --sha256 options are required.');
    }

    $directory = is_string($options['directory'] ?? null) ? $options['directory'] : sys_get_temp_dir() . '/crypt_shared';

    if (! is_dir($directory) && ! mkdir($directory, 0o755, true) && ! is_dir($directory)) {
        throw new RuntimeException(sprintf('Unable to create the directory "%s".', $directory));
    }

    $library = findLibrary($directory);

    if ($library !== null) {
        fwrite(STDERR, sprintf('crypt_shared is already installed at %s.%s', $library, PHP_EOL));
    } else {
        fwrite(STDERR, sprintf('Downloading crypt_shared from %s.%s', $url, PHP_EOL));

        $archive = $directory . '/crypt_shared.tgz';
        downloadArchive($url, $archive);

        $checksum = hash_file('sha256', $archive);
        if ($checksum !== $sha256) {
            throw new RuntimeException(sprintf('Checksum mismatch for "%s": expected %s, got %s.', $url, $sha256, $checksum));
        }

        extractArchive($archive, $directory);
        unlink($archive);

        $library = findLibrary($directory);
        if ($library === null) {
            throw new RuntimeException(sprintf('No mongo_crypt_v1.so found in the archive extracted to "%s".', $directory));
        }
    }

    echo $library, PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);

    exit(1);
}
