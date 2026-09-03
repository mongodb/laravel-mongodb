#!/usr/bin/env php
<?php

/**
 * Validates PHP syntax of eligible code blocks in the laravel-mongodb skill markdown files.
 * Skips non-executable fragments (bare config arrays, comparison snippets without statements).
 *
 * Usage (from repo root):
 *   php tests/skills/laravel-mongodb/validate-php-examples.php [path/to/skill]
 */

$skillDir = $argv[1] ?? 'resources/boost/skills/laravel-mongodb';

if (! is_dir($skillDir)) {
    fwrite(STDERR, 'Error: directory not found: ' . $skillDir . "\n");
    exit(2);
}

$mdFiles = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($skillDir, FilesystemIterator::SKIP_DOTS),
);

$passed  = 0;
$skipped = 0;
$failed  = 0;
$errors  = [];

foreach ($mdFiles as $file) {
    if ($file->getExtension() !== 'md') {
        continue;
    }

    $content = file_get_contents($file->getPathname());
    if ($content === false) {
        fwrite(STDERR, 'Error: cannot read ' . $file->getPathname() . "\n");
        exit(2);
    }

    // Match ```php blocks with Unix or Windows line endings
    preg_match_all('/```php\r?\n(.*?)```/s', $content, $matches);

    foreach ($matches[1] as $i => $snippet) {
        $blockNum = $i + 1;
        $relPath  = $file->getPathname();
        $label    = $relPath . ' (block ' . $blockNum . ')';

        $snippet = rtrim(str_replace("\r\n", "\n", $snippet));

        // Skip non-executable fragments (comparison snippets, bare import lists)
        if (
            ! str_contains($snippet, '$')
            && ! str_contains($snippet, 'class ')
            && ! str_contains($snippet, 'function ')
            && ! str_contains($snippet, 'return ')
            && ! str_contains($snippet, 'declare')
            && ! str_contains($snippet, 'namespace')
        ) {
            $skipped++;
            continue;
        }

        // Wrap orphan class-body snippets (properties/methods without a class declaration)
        $raw          = ltrim($snippet);
        $hasClassBody = (bool) preg_match('/^\s*(protected|public|private)\s+[\$\w]/m', $raw);
        $hasClass     = str_contains($raw, 'class ');
        $hasPHPTag    = str_starts_with($raw, '<?');

        if ($hasClassBody && ! $hasClass) {
            $inner   = $hasPHPTag ? preg_replace('/^<\?php\s*/i', '', $raw) : $raw;
            $snippet = "<?php\nclass __Wrapper__ {\n" . $inner . "\n}";
        } elseif (! $hasPHPTag) {
            $snippet = "<?php\n" . $snippet;
        }

        // php -l works with any extension; skip the rename to avoid failure edge cases
        $tmpFile = tempnam(sys_get_temp_dir(), 'skill_php_');
        if ($tmpFile === false) {
            fwrite(STDERR, "Error: could not create temp file\n");
            exit(2);
        }

        if (file_put_contents($tmpFile, $snippet) === false) {
            unlink($tmpFile);
            fwrite(STDERR, "Error: could not write temp file\n");
            exit(2);
        }

        $output   = [];
        $exitCode = 0;
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($tmpFile) . ' 2>&1', $output, $exitCode);
        unlink($tmpFile);

        if ($exitCode === 0) {
            $passed++;
        } else {
            $failed++;
            $outputClean = implode("\n", array_map(
                fn($l) => str_replace($tmpFile, '<snippet>', $l),
                $output,
            ));
            $relevantLines = array_filter(
                explode("\n", $outputClean),
                fn($l) => ! str_starts_with(trim($l), 'No syntax errors'),
            );
            $errors[] = 'FAIL  ' . $label . "\n" .
                implode("\n", array_map(fn($l) => '      ' . $l, $relevantLines));
        }
    }
}

foreach ($errors as $error) {
    echo $error . "\n\n";
}

$total = $passed + $failed + $skipped;
echo 'Results: ' . $passed . ' passed, ' . $failed . ' failed, ' . $skipped
    . ' skipped (partial/comparison snippets), ' . $total . " total\n";

exit($failed > 0 ? 1 : 0);
