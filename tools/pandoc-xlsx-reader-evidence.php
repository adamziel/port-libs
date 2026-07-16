#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use PortLibs\Pandoc\XlsxUpstreamReaderEvidence;

$usage = static function (): string {
    return <<<'TEXT'
Usage: php tools/pandoc-xlsx-reader-evidence.php [options]

Options:
  --json                          Emit JSON instead of text.
  --repo-root PATH                Repository root. Defaults to the parent of tools/.
  --upstream-root PATH            Optional upstream Pandoc checkout root.
                                  Defaults to .upstream-cache/pandoc-current.
  --require-test-count N          Exit 1 unless Tests.Readers.Xlsx has exactly N comparisons.
  --require-fixture-pair-count N  Exit 1 unless test/xlsx-reader has exactly N XLSX/native pairs.
  --require-static-current-evidence
                                  Exit 1 unless checked-in XLSX fixture hashes are valid.
  --require-no-validation-issues  Exit 1 when denominator validation reports any issue.
  --help                          Show this help.

This is a denominator/evidence gate for the upstream XLSX reader fixture set.
It does not run Cabal/Tasty, execute pandoc, or claim writer parity.
TEXT;
};

try {
    $repoRoot = dirname(__DIR__);
    $upstreamRoot = XlsxUpstreamReaderEvidence::DEFAULT_RELATIVE_UPSTREAM_ROOT;
    $json = false;
    $requiredTestCount = null;
    $requiredFixturePairCount = null;
    $requireStaticCurrentEvidence = false;
    $requireNoValidationIssues = false;
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
        if ($arg === '--require-no-validation-issues') {
            $requireNoValidationIssues = true;
            continue;
        }
        if ($arg === '--require-static-current-evidence') {
            $requireStaticCurrentEvidence = true;
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
        if ($arg === '--upstream-root') {
            $upstreamRoot = $nextValue('--upstream-root');
            continue;
        }
        if (str_starts_with($arg, '--upstream-root=')) {
            $upstreamRoot = substr($arg, strlen('--upstream-root='));
            continue;
        }
        if ($arg === '--require-test-count') {
            $raw = $nextValue('--require-test-count');
            if (!ctype_digit($raw)) {
                throw new InvalidArgumentException('--require-test-count must be a non-negative integer');
            }
            $requiredTestCount = (int) $raw;
            continue;
        }
        if (str_starts_with($arg, '--require-test-count=')) {
            $raw = substr($arg, strlen('--require-test-count='));
            if (!ctype_digit($raw)) {
                throw new InvalidArgumentException('--require-test-count must be a non-negative integer');
            }
            $requiredTestCount = (int) $raw;
            continue;
        }
        if ($arg === '--require-fixture-pair-count') {
            $raw = $nextValue('--require-fixture-pair-count');
            if (!ctype_digit($raw)) {
                throw new InvalidArgumentException('--require-fixture-pair-count must be a non-negative integer');
            }
            $requiredFixturePairCount = (int) $raw;
            continue;
        }
        if (str_starts_with($arg, '--require-fixture-pair-count=')) {
            $raw = substr($arg, strlen('--require-fixture-pair-count='));
            if (!ctype_digit($raw)) {
                throw new InvalidArgumentException('--require-fixture-pair-count must be a non-negative integer');
            }
            $requiredFixturePairCount = (int) $raw;
            continue;
        }

        throw new InvalidArgumentException("Unknown option: {$arg}");
    }

    $report = (new XlsxUpstreamReaderEvidence($repoRoot, $upstreamRoot))->report();
    if ($json) {
        fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) . PHP_EOL);
    } else {
        fwrite(STDOUT, XlsxUpstreamReaderEvidence::formatTextReport($report));
    }

    if (
        $requiredTestCount !== null
        && !XlsxUpstreamReaderEvidence::hasRequiredReaderTestCount($report, $requiredTestCount)
    ) {
        fwrite(STDERR, "pandoc-xlsx-reader-evidence: reader test comparison count did not match {$requiredTestCount}\n");
        exit(1);
    }

    if (
        $requiredFixturePairCount !== null
        && !XlsxUpstreamReaderEvidence::hasRequiredFixturePairCount($report, $requiredFixturePairCount)
    ) {
        fwrite(STDERR, "pandoc-xlsx-reader-evidence: fixture pair count did not match {$requiredFixturePairCount}\n");
        exit(1);
    }

    if ($requireStaticCurrentEvidence && !XlsxUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($report)) {
        fwrite(STDERR, "pandoc-xlsx-reader-evidence: checked-in current XLSX fixture evidence is invalid\n");
        exit(1);
    }

    if ($requireNoValidationIssues && !XlsxUpstreamReaderEvidence::hasNoValidationIssues($report)) {
        fwrite(STDERR, "pandoc-xlsx-reader-evidence: upstream XLSX reader denominator validation reported issues\n");
        exit(1);
    }

    exit(0);
} catch (InvalidArgumentException $exception) {
    fwrite(STDERR, 'pandoc-xlsx-reader-evidence: ' . $exception->getMessage() . PHP_EOL);
    fwrite(STDERR, $usage() . PHP_EOL);
    exit(2);
} catch (Throwable $throwable) {
    fwrite(STDERR, 'pandoc-xlsx-reader-evidence: ' . $throwable::class . ': ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}
