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
  --help            Show this help.

The audit is evidence-only. It inventories upstream golden DOCX packages and the
local writer support status. It does not generate DOCX output or claim writer
golden package parity.
TEXT;
};

try {
    $repoRoot = dirname(__DIR__);
    $docxDir = DocxWriterGoldenManifest::DEFAULT_RELATIVE_DOCX_DIR;
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

        throw new InvalidArgumentException("Unknown option: {$arg}");
    }

    $report = (new DocxWriterGoldenManifest($repoRoot, $docxDir))->report();

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
