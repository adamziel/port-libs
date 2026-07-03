#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use PortLibs\Pandoc\MarkdownUpstreamReaderEvidence;

$usage = static function (): string {
    return <<<'TEXT'
Usage: php tools/pandoc-markdown-reader-evidence.php [options]

Options:
  --json                              Emit JSON instead of text.
  --repo-root PATH                    Repository root. Defaults to the parent of tools/.
  --checked-in-fixtures               Use the checked-in selected Markdown fixture
                                      snapshots without requiring an upstream checkout.
  --upstream-root PATH                Optional upstream Pandoc checkout root.
                                      Defaults to .upstream-cache/pandoc-current.
  --require-selected-fixture-count N  Exit 1 unless the checked-in selected fixture count is N.
  --require-static-current-evidence   Exit 1 unless checked-in fixture hashes and coverage tests are valid.
  --require-native-mapped-parity N    Exit 1 unless the Markdown/native AST gate observes N mapped pairs.
  --require-runner-not-run            Exit 1 unless upstream runner evidence is structured as not-run.
  --require-runner-plan               Exit 1 unless upstream runner evidence includes the pinned
                                      non-executed test:test-pandoc Markdown command plan.
  --require-no-validation-issues      Exit 1 when hydrated-upstream validation reports any issue.
  --help                              Show this help.

This is a focused evidence gate for selected checked-in Markdown reader fixtures.
It does not run Cabal/Tasty, execute pandoc, or claim full Markdown dialect parity.
TEXT;
};

try {
    $repoRoot = dirname(__DIR__);
    $upstreamRoot = MarkdownUpstreamReaderEvidence::DEFAULT_RELATIVE_UPSTREAM_ROOT;
    $useCheckedInFixtures = false;
    $upstreamRootArgumentWasProvided = false;
    $json = false;
    $requiredSelectedFixtureCount = null;
    $requiredNativeMappedParity = null;
    $requireStaticCurrentEvidence = false;
    $requireRunnerNotRun = false;
    $requireRunnerPlan = false;
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
        if ($arg === '--require-static-current-evidence') {
            $requireStaticCurrentEvidence = true;
            continue;
        }
        if ($arg === '--require-runner-not-run') {
            $requireRunnerNotRun = true;
            continue;
        }
        if ($arg === '--require-runner-plan') {
            $requireRunnerPlan = true;
            continue;
        }
        if ($arg === '--require-no-validation-issues') {
            $requireNoValidationIssues = true;
            continue;
        }
        if ($arg === '--checked-in-fixtures') {
            $useCheckedInFixtures = true;
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
            $upstreamRootArgumentWasProvided = true;
            continue;
        }
        if (str_starts_with($arg, '--upstream-root=')) {
            $upstreamRoot = substr($arg, strlen('--upstream-root='));
            $upstreamRootArgumentWasProvided = true;
            continue;
        }
        if ($arg === '--require-selected-fixture-count') {
            $raw = $nextValue('--require-selected-fixture-count');
            if (!ctype_digit($raw)) {
                throw new InvalidArgumentException('--require-selected-fixture-count must be a non-negative integer');
            }
            $requiredSelectedFixtureCount = (int) $raw;
            continue;
        }
        if ($arg === '--require-native-mapped-parity') {
            $raw = $nextValue('--require-native-mapped-parity');
            if (!ctype_digit($raw)) {
                throw new InvalidArgumentException('--require-native-mapped-parity must be a non-negative integer');
            }
            $requiredNativeMappedParity = (int) $raw;
            continue;
        }
        if (str_starts_with($arg, '--require-selected-fixture-count=')) {
            $raw = substr($arg, strlen('--require-selected-fixture-count='));
            if (!ctype_digit($raw)) {
                throw new InvalidArgumentException('--require-selected-fixture-count must be a non-negative integer');
            }
            $requiredSelectedFixtureCount = (int) $raw;
            continue;
        }
        if (str_starts_with($arg, '--require-native-mapped-parity=')) {
            $raw = substr($arg, strlen('--require-native-mapped-parity='));
            if (!ctype_digit($raw)) {
                throw new InvalidArgumentException('--require-native-mapped-parity must be a non-negative integer');
            }
            $requiredNativeMappedParity = (int) $raw;
            continue;
        }

        throw new InvalidArgumentException("Unknown option: {$arg}");
    }

    if ($useCheckedInFixtures && $upstreamRootArgumentWasProvided) {
        throw new InvalidArgumentException('--checked-in-fixtures cannot be combined with --upstream-root');
    }

    if ($useCheckedInFixtures) {
        $upstreamRoot = 'missing-upstream-root-for-static-markdown-gate';
    }

    $report = (new MarkdownUpstreamReaderEvidence($repoRoot, $upstreamRoot))->report();
    if ($json) {
        fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) . PHP_EOL);
    } else {
        fwrite(STDOUT, MarkdownUpstreamReaderEvidence::formatTextReport($report));
    }

    if (
        $requiredSelectedFixtureCount !== null
        && !MarkdownUpstreamReaderEvidence::hasRequiredSelectedFixtureCount($report, $requiredSelectedFixtureCount)
    ) {
        fwrite(STDERR, "pandoc-markdown-reader-evidence: selected fixture count did not match {$requiredSelectedFixtureCount}\n");
        exit(1);
    }
    if ($requireStaticCurrentEvidence && !MarkdownUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($report)) {
        fwrite(STDERR, "pandoc-markdown-reader-evidence: checked-in current Markdown fixture evidence is invalid\n");
        exit(1);
    }
    if (
        $requiredNativeMappedParity !== null
        && !MarkdownUpstreamReaderEvidence::hasRequiredNativeMappedParity($report, $requiredNativeMappedParity)
    ) {
        fwrite(STDERR, "pandoc-markdown-reader-evidence: native mapped parity did not match {$requiredNativeMappedParity}\n");
        exit(1);
    }
    if ($requireRunnerNotRun && !MarkdownUpstreamReaderEvidence::hasRunnerNotRunEvidence($report)) {
        fwrite(STDERR, "pandoc-markdown-reader-evidence: runner not-run evidence is invalid\n");
        exit(1);
    }
    if ($requireRunnerPlan && !MarkdownUpstreamReaderEvidence::hasRunnerPlanEvidence($report)) {
        fwrite(STDERR, "pandoc-markdown-reader-evidence: runner command-plan evidence is invalid\n");
        exit(1);
    }
    if ($requireNoValidationIssues && !MarkdownUpstreamReaderEvidence::hasNoValidationIssues($report)) {
        fwrite(STDERR, "pandoc-markdown-reader-evidence: upstream Markdown reader validation reported issues\n");
        exit(1);
    }

    exit(0);
} catch (InvalidArgumentException $exception) {
    fwrite(STDERR, 'pandoc-markdown-reader-evidence: ' . $exception->getMessage() . PHP_EOL);
    fwrite(STDERR, $usage() . PHP_EOL);
    exit(2);
} catch (Throwable $throwable) {
    fwrite(STDERR, 'pandoc-markdown-reader-evidence: ' . $throwable::class . ': ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}
