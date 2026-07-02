#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use PortLibs\Pandoc\DocxUpstreamFocusedReaderEvidence;

$usage = static function (): string {
    return <<<'TEXT'
Usage: php tools/pandoc-docx-focused-reader-evidence.php [options]

Options:
  --json                         Emit JSON instead of text.
  --repo-root PATH               Repository root. Defaults to the parent of tools/.
  --docx-dir PATH                Optional upstream DOCX corpus directory.
                                 Defaults to .upstream-cache/pandoc-current/test/docx.
  --write-report PATH            Write JSON report to PATH.
  --require-covered N            Exit 1 unless focused coverage is at least N cases.
  --require-targeted-checks N    Exit 1 unless at least N targeted DOCX case checks pass.
  --require-no-targeted-failures Exit 1 when any targeted DOCX check fails.
  --help                         Show this help.

This is an evidence gate for upstream DOCX reader cases outside the local 74/74
parser-acceptance gate. It does not run Cabal/Tasty or claim full DOCX parity.
TEXT;
};

try {
    $repoRoot = dirname(__DIR__);
    $docxDir = DocxUpstreamFocusedReaderEvidence::DEFAULT_RELATIVE_DOCX_DIR;
    $json = false;
    $writeReport = null;
    $requiredCovered = null;
    $requiredTargetedChecks = null;
    $requireNoTargetedFailures = false;
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
        if ($arg === '--require-no-targeted-failures') {
            $requireNoTargetedFailures = true;
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
        if ($arg === '--write-report') {
            $writeReport = $nextValue('--write-report');
            continue;
        }
        if (str_starts_with($arg, '--write-report=')) {
            $writeReport = substr($arg, strlen('--write-report='));
            continue;
        }
        if ($arg === '--require-covered') {
            $raw = $nextValue('--require-covered');
            if (!ctype_digit($raw)) {
                throw new InvalidArgumentException('--require-covered must be a non-negative integer');
            }
            $requiredCovered = (int) $raw;
            continue;
        }
        if (str_starts_with($arg, '--require-covered=')) {
            $raw = substr($arg, strlen('--require-covered='));
            if (!ctype_digit($raw)) {
                throw new InvalidArgumentException('--require-covered must be a non-negative integer');
            }
            $requiredCovered = (int) $raw;
            continue;
        }
        if ($arg === '--require-targeted-checks') {
            $raw = $nextValue('--require-targeted-checks');
            if (!ctype_digit($raw)) {
                throw new InvalidArgumentException('--require-targeted-checks must be a non-negative integer');
            }
            $requiredTargetedChecks = (int) $raw;
            continue;
        }
        if (str_starts_with($arg, '--require-targeted-checks=')) {
            $raw = substr($arg, strlen('--require-targeted-checks='));
            if (!ctype_digit($raw)) {
                throw new InvalidArgumentException('--require-targeted-checks must be a non-negative integer');
            }
            $requiredTargetedChecks = (int) $raw;
            continue;
        }

        throw new InvalidArgumentException("Unknown option: {$arg}");
    }

    $report = (new DocxUpstreamFocusedReaderEvidence($repoRoot, $docxDir))->report();
    $encoded = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) . PHP_EOL;

    if (is_string($writeReport)) {
        $path = str_starts_with($writeReport, DIRECTORY_SEPARATOR)
            ? $writeReport
            : rtrim($repoRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $writeReport;
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create report directory: {$directory}");
        }
        if (file_put_contents($path, $encoded) === false) {
            throw new RuntimeException("Unable to write report: {$path}");
        }
    }

    if ($json) {
        fwrite(STDOUT, $encoded);
    } else {
        fwrite(STDOUT, DocxUpstreamFocusedReaderEvidence::formatTextReport($report));
    }

    if (
        $requiredCovered !== null
        && !DocxUpstreamFocusedReaderEvidence::hasRequiredFocusedCoverage($report, $requiredCovered)
    ) {
        fwrite(STDERR, "pandoc-docx-focused-reader-evidence: focused coverage is below {$requiredCovered} cases\n");
        exit(1);
    }

    if (
        $requiredTargetedChecks !== null
        && !DocxUpstreamFocusedReaderEvidence::hasRequiredTargetedChecks($report, $requiredTargetedChecks)
    ) {
        fwrite(STDERR, "pandoc-docx-focused-reader-evidence: targeted DOCX checks are below {$requiredTargetedChecks} passed cases\n");
        exit(1);
    }

    if ($requireNoTargetedFailures && DocxUpstreamFocusedReaderEvidence::hasTargetedCheckFailures($report)) {
        fwrite(STDERR, "pandoc-docx-focused-reader-evidence: targeted DOCX check failures were reported\n");
        exit(1);
    }

    exit(0);
} catch (InvalidArgumentException $exception) {
    fwrite(STDERR, 'pandoc-docx-focused-reader-evidence: ' . $exception->getMessage() . PHP_EOL);
    fwrite(STDERR, $usage() . PHP_EOL);
    exit(2);
} catch (Throwable $throwable) {
    fwrite(STDERR, 'pandoc-docx-focused-reader-evidence: ' . $throwable::class . ': ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}
