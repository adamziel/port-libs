#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use PortLibs\Pandoc\DocxWriterGoldenManifest;

$usage = static function (): string {
    return <<<'TEXT'
Usage: php tools/pandoc-docx-writer-golden-audit.php [options]

Options:
  --json            Emit JSON instead of text.
  --repo-root PATH  Repository root. Defaults to the parent of tools/.
  --docx-dir PATH   Upstream DOCX corpus directory, relative to repo root unless absolute.
                    Defaults to .upstream-cache/pandoc-current/test/docx.
  --generated-dir PATH
                    Directory containing generated DOCX packages to compare
                    against golden/*.docx by stable package semantics.
                    Relative paths are resolved from the repository root.
  --generate-supported-dir PATH
                    Generate DOCX packages for the pinned upstream writer
                    golden native inputs supported by the bounded PHP writer.
                    Relative paths are resolved from the repository root.
                    When --generated-dir is omitted, this directory is also
                    used for stable package comparison.
  --help            Show this help.

The audit is evidence-only. It inventories upstream golden DOCX packages and the
local writer support status. When --generate-supported-dir is supplied, it uses
the bounded PHP DocxWriter to generate packages from the pinned upstream writer
golden native inputs it can read. When --generated-dir or --generate-supported-dir
is supplied, it compares those generated packages to golden/*.docx by stable
package semantics. It does not claim writer parity without generated comparisons.
TEXT;
};

try {
    $repoRoot = dirname(__DIR__);
    $docxDir = DocxWriterGoldenManifest::DEFAULT_RELATIVE_DOCX_DIR;
    $generatedDir = null;
    $generationOutputDir = null;
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
        if ($arg === '--generated-dir') {
            $generatedDir = $nextValue('--generated-dir');
            continue;
        }
        if (str_starts_with($arg, '--generated-dir=')) {
            $generatedDir = substr($arg, strlen('--generated-dir='));
            continue;
        }
        if ($arg === '--generate-supported-dir') {
            $generationOutputDir = $nextValue('--generate-supported-dir');
            continue;
        }
        if (str_starts_with($arg, '--generate-supported-dir=')) {
            $generationOutputDir = substr($arg, strlen('--generate-supported-dir='));
            continue;
        }

        throw new InvalidArgumentException("Unknown option: {$arg}");
    }

    $report = (new DocxWriterGoldenManifest($repoRoot, $docxDir, 8, $generatedDir, $generationOutputDir))->report();

    if ($json) {
        fwrite(STDOUT, json_encode(
            $report,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        ) . PHP_EOL);
    } else {
        fwrite(STDOUT, DocxWriterGoldenManifest::formatTextReport($report));
    }

    exit(0);
} catch (InvalidArgumentException $exception) {
    fwrite(STDERR, 'pandoc-docx-writer-golden-audit: ' . $exception->getMessage() . PHP_EOL);
    fwrite(STDERR, $usage() . PHP_EOL);
    exit(2);
} catch (Throwable $throwable) {
    fwrite(STDERR, 'pandoc-docx-writer-golden-audit: ' . $throwable::class . ': ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}
