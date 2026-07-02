#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use PortLibs\Pandoc\DocxUpstreamCacheManifest;

$usage = static function (): string {
    return <<<'TEXT'
Usage: php tools/pandoc-docx-cache-manifest.php [options]

Options:
  --json                 Emit JSON instead of text.
  --repo-root PATH       Repository root. Defaults to the parent of tools/.
  --docx-dir PATH        Upstream DOCX corpus directory, relative to repo root unless absolute.
                         Defaults to .upstream-cache/pandoc-current/test/docx.
  --upstream-root PATH   Upstream Pandoc checkout root for git provenance.
                         Defaults to the inferred parent of test/docx.
  --upstream-commit SHA  Expected/observed upstream commit when git metadata is unavailable.
  --help                 Show this help.

The report is metadata-only. It records paths, stems, byte counts, and SHA-256
hashes for the optional local upstream DOCX/native/golden cache. It does not
check in package bytes or assert parser acceptance, AST equality, upstream
runner parity, writer golden parity, or full DOCX/OpenXML parity.
TEXT;
};

$absolutePath = static function (string $path, string $repoRoot): string {
    if ($path === '') {
        return $path;
    }
    if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
        return rtrim($path, DIRECTORY_SEPARATOR);
    }

    return rtrim($repoRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
};

$displayPath = static function (string $path, string $repoRoot): string {
    $root = rtrim($repoRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (str_starts_with($path, $root)) {
        return str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root)));
    }

    return $path;
};

$inferUpstreamRoot = static function (string $absoluteDocxDir): string {
    $normalized = str_replace(DIRECTORY_SEPARATOR, '/', rtrim($absoluteDocxDir, DIRECTORY_SEPARATOR));
    if (str_ends_with($normalized, '/test/docx')) {
        return dirname(dirname($absoluteDocxDir));
    }

    return dirname(dirname($absoluteDocxDir));
};

$gitOutput = static function (string $workingDirectory, string $arguments): ?array {
    if ($workingDirectory === '' || !is_dir($workingDirectory)) {
        return null;
    }

    $command = 'git -C ' . escapeshellarg($workingDirectory) . ' ' . $arguments;
    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);
    if ($exitCode !== 0) {
        return null;
    }

    return $output;
};

try {
    $repoRoot = dirname(__DIR__);
    $docxDir = DocxUpstreamCacheManifest::DEFAULT_RELATIVE_DOCX_DIR;
    $upstreamRoot = null;
    $upstreamCommit = DocxUpstreamCacheManifest::CURRENT_UPSTREAM_COMMIT;
    $json = false;
    $args = array_slice($argv, 1);

    for ($i = 0, $count = count($args); $i < $count; ++$i) {
        $arg = $args[$i];
        $nextValue = static function (string $name) use ($args, &$i, $count): string {
            if ($i + 1 >= $count) {
                throw new InvalidArgumentException("Missing value for {$name}");
            }

            ++$i;

            return $args[$i];
        };

        if ($arg === '--help' || $arg === '-h') {
            fwrite(STDOUT, $usage() . PHP_EOL);
            exit(0);
        }
        if ($arg === '--json') {
            $json = true;
            continue;
        }
        if ($arg === '--repo-root') {
            $repoRoot = $nextValue('--repo-root');
            continue;
        }
        if (str_starts_with($arg, '--repo-root=')) {
            $repoRoot = substr($arg, strlen('--repo-root='));
            continue;
        }
        if ($arg === '--docx-dir') {
            $docxDir = $nextValue('--docx-dir');
            continue;
        }
        if (str_starts_with($arg, '--docx-dir=')) {
            $docxDir = substr($arg, strlen('--docx-dir='));
            continue;
        }
        if ($arg === '--upstream-root') {
            $upstreamRoot = $nextValue('--upstream-root');
            continue;
        }
        if (str_starts_with($arg, '--upstream-root=')) {
            $upstreamRoot = substr($arg, strlen('--upstream-root='));
            continue;
        }
        if ($arg === '--upstream-commit') {
            $upstreamCommit = $nextValue('--upstream-commit');
            continue;
        }
        if (str_starts_with($arg, '--upstream-commit=')) {
            $upstreamCommit = substr($arg, strlen('--upstream-commit='));
            continue;
        }

        throw new InvalidArgumentException("Unknown option: {$arg}");
    }

    $absoluteDocxDir = $absolutePath($docxDir, $repoRoot);
    $absoluteUpstreamRoot = $upstreamRoot === null
        ? $inferUpstreamRoot($absoluteDocxDir)
        : $absolutePath($upstreamRoot, $repoRoot);
    $revParse = $gitOutput($absoluteUpstreamRoot, 'rev-parse HEAD');
    if (is_array($revParse) && isset($revParse[0]) && is_string($revParse[0]) && $revParse[0] !== '') {
        $upstreamCommit = $revParse[0];
    }

    $statusRows = $gitOutput($absoluteUpstreamRoot, 'status --porcelain -- ' . escapeshellarg('test/docx'));
    $sourceProvenance = [
        'observedUpstreamCommit' => $upstreamCommit,
        'workingTreeCleanForTestDocx' => is_array($statusRows) ? $statusRows === [] : null,
        'upstreamRootDisplay' => $displayPath($absoluteUpstreamRoot, $repoRoot),
    ];

    $report = (new DocxUpstreamCacheManifest($repoRoot, $docxDir))->report($sourceProvenance);

    if ($json) {
        fwrite(STDOUT, json_encode(
            $report,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        ) . PHP_EOL);
    } else {
        fwrite(STDOUT, DocxUpstreamCacheManifest::formatTextReport($report));
    }

    exit(0);
} catch (InvalidArgumentException $exception) {
    fwrite(STDERR, 'pandoc-docx-cache-manifest: ' . $exception->getMessage() . PHP_EOL);
    fwrite(STDERR, $usage() . PHP_EOL);
    exit(2);
} catch (Throwable $throwable) {
    fwrite(STDERR, 'pandoc-docx-cache-manifest: ' . $throwable::class . ': ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}
