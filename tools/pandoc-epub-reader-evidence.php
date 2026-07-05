#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use PortLibs\Pandoc\EpubUpstreamReaderEvidence;

$usage = static function (): string {
    return <<<'TEXT'
Usage: php tools/pandoc-epub-reader-evidence.php [options]

Options:
  --json                                  Emit JSON instead of text.
  --repo-root PATH                        Repository root. Defaults to the parent of tools/.
  --checked-in-fixtures                   Use the checked-in current EPUB reader fixture snapshot
                                          for both upstream reader tests and EPUB fixtures.
  --upstream-root PATH                    Optional upstream Pandoc checkout root.
                                          Defaults to .upstream-cache/pandoc-current.
  --fixture-base PATH                     Optional checked-in EPUB fixture base.
                                          When supplied, EPUB files are resolved from PATH/epub
                                          and the upstream reader source file is not required.
  --require-test-count N                  Exit 1 unless Tests.Readers.EPUB has exactly N media-bag tests.
  --require-fixture-reference-count N     Exit 1 unless Tests.Readers.EPUB references exactly N EPUB fixtures.
  --require-expected-media-item-count N   Exit 1 unless expected media-bag tuples total exactly N items.
  --require-referenced-fixture-identity   Exit 1 unless the checked-in current referenced EPUB
                                          fixture identity snapshot matches.
  --require-static-current-signature      Exit 1 unless the checked-in current static reader
                                          denominator signature matches the expected snapshot.
  --require-native-ast-package-parity     Exit 1 unless the checked-in current EPUB package,
                                          package-feature, and native AST parity snapshots match.
  --require-runner-not-run                Exit 1 unless upstream runner evidence is structured as not-run.
  --require-runner-plan                   Exit 1 unless upstream runner evidence includes the pinned
                                          non-executed test:test-pandoc EPUB command plan.
  --runner-result-artifact PATH           Validate a captured upstream runner result JSON artifact
                                          and its transcript file identities.
  --require-runner-result-artifact        Exit 1 unless the supplied runner result artifact is valid.
  --pandoc-bin PATH                       Include checked-in current EPUB executable/native AST
                                          parity evidence using PATH as the pandoc executable.
                                          When omitted but executable parity is required, PATH
                                          defaults to PANDOC_BIN or `pandoc`.
  --require-executable-native-ast-parity  Exit 1 unless local PHP reader output, local pandoc
                                          executable output, and checked-in .native fixtures match
                                          by normalized AST for all checked-in current EPUB inputs.
  --require-pandoc-version VERSION        Exit 1 unless executable parity evidence observed VERSION
                                          as the pandoc executable version line.
  --require-no-validation-issues          Exit 1 when denominator validation reports any issue.
  --help                                  Show this help.

This is a denominator/evidence gate for the upstream EPUB reader media-bag tests.
It does not run Cabal/Tasty, execute pandoc, or claim writer parity.
TEXT;
};

try {
    $repoRoot = dirname(__DIR__);
    $checkedInFixtureRoot = 'lanes/pandoc/fixtures/upstream-current-epub-reader';
    $upstreamRoot = EpubUpstreamReaderEvidence::DEFAULT_RELATIVE_UPSTREAM_ROOT;
    $fixtureBase = null;
    $useCheckedInFixtures = false;
    $upstreamRootArgumentWasProvided = false;
    $fixtureBaseArgumentWasProvided = false;
    $json = false;
    $requiredTestCount = null;
    $requiredFixtureReferenceCount = null;
    $requiredExpectedMediaItemCount = null;
    $requireReferencedFixtureIdentity = false;
    $requireStaticCurrentSignature = false;
    $requireNativeAstPackageParity = false;
    $requireRunnerNotRun = false;
    $requireRunnerPlan = false;
    $requireRunnerResultArtifact = false;
    $requireExecutableNativeAstParity = false;
    $requireNoValidationIssues = false;
    $runnerResultArtifact = null;
    $pandocBin = null;
    $requiredPandocVersion = null;
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
        if ($arg === '--require-runner-not-run') {
            $requireRunnerNotRun = true;
            continue;
        }
        if ($arg === '--require-runner-plan') {
            $requireRunnerPlan = true;
            continue;
        }
        if ($arg === '--require-runner-result-artifact') {
            $requireRunnerResultArtifact = true;
            continue;
        }
        if ($arg === '--require-executable-native-ast-parity') {
            $requireExecutableNativeAstParity = true;
            continue;
        }
        if ($arg === '--require-static-current-signature') {
            $requireStaticCurrentSignature = true;
            continue;
        }
        if ($arg === '--require-native-ast-package-parity') {
            $requireNativeAstPackageParity = true;
            continue;
        }
        if ($arg === '--require-referenced-fixture-identity') {
            $requireReferencedFixtureIdentity = true;
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
        if ($arg === '--fixture-base') {
            $fixtureBase = $nextValue('--fixture-base');
            $fixtureBaseArgumentWasProvided = true;
            continue;
        }
        if (str_starts_with($arg, '--fixture-base=')) {
            $fixtureBase = substr($arg, strlen('--fixture-base='));
            $fixtureBaseArgumentWasProvided = true;
            continue;
        }
        if ($arg === '--runner-result-artifact') {
            $runnerResultArtifact = $nextValue('--runner-result-artifact');
            continue;
        }
        if (str_starts_with($arg, '--runner-result-artifact=')) {
            $runnerResultArtifact = substr($arg, strlen('--runner-result-artifact='));
            continue;
        }
        if ($arg === '--pandoc-bin') {
            $pandocBin = $nextValue('--pandoc-bin');
            continue;
        }
        if (str_starts_with($arg, '--pandoc-bin=')) {
            $pandocBin = substr($arg, strlen('--pandoc-bin='));
            continue;
        }
        if ($arg === '--require-pandoc-version') {
            $requiredPandocVersion = $nextValue('--require-pandoc-version');
            continue;
        }
        if (str_starts_with($arg, '--require-pandoc-version=')) {
            $requiredPandocVersion = substr($arg, strlen('--require-pandoc-version='));
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
        if ($arg === '--require-fixture-reference-count') {
            $raw = $nextValue('--require-fixture-reference-count');
            if (!ctype_digit($raw)) {
                throw new InvalidArgumentException('--require-fixture-reference-count must be a non-negative integer');
            }
            $requiredFixtureReferenceCount = (int) $raw;
            continue;
        }
        if (str_starts_with($arg, '--require-fixture-reference-count=')) {
            $raw = substr($arg, strlen('--require-fixture-reference-count='));
            if (!ctype_digit($raw)) {
                throw new InvalidArgumentException('--require-fixture-reference-count must be a non-negative integer');
            }
            $requiredFixtureReferenceCount = (int) $raw;
            continue;
        }
        if ($arg === '--require-expected-media-item-count') {
            $raw = $nextValue('--require-expected-media-item-count');
            if (!ctype_digit($raw)) {
                throw new InvalidArgumentException('--require-expected-media-item-count must be a non-negative integer');
            }
            $requiredExpectedMediaItemCount = (int) $raw;
            continue;
        }
        if (str_starts_with($arg, '--require-expected-media-item-count=')) {
            $raw = substr($arg, strlen('--require-expected-media-item-count='));
            if (!ctype_digit($raw)) {
                throw new InvalidArgumentException('--require-expected-media-item-count must be a non-negative integer');
            }
            $requiredExpectedMediaItemCount = (int) $raw;
            continue;
        }

        throw new InvalidArgumentException("Unknown option: {$arg}");
    }

    if ($useCheckedInFixtures && ($upstreamRootArgumentWasProvided || $fixtureBaseArgumentWasProvided)) {
        throw new InvalidArgumentException('--checked-in-fixtures cannot be combined with --upstream-root or --fixture-base');
    }

    if ($useCheckedInFixtures) {
        $upstreamRoot = $checkedInFixtureRoot;
        $fixtureBase = $checkedInFixtureRoot;
    }

    if ($pandocBin === '') {
        throw new InvalidArgumentException('--pandoc-bin must not be empty');
    }
    if ($requiredPandocVersion === '') {
        throw new InvalidArgumentException('--require-pandoc-version must not be empty');
    }

    $includeExecutableNativeAstParity = $pandocBin !== null
        || $requireExecutableNativeAstParity
        || $requiredPandocVersion !== null;

    $report = (new EpubUpstreamReaderEvidence(
        $repoRoot,
        $upstreamRoot,
        $fixtureBase,
        $runnerResultArtifact,
        $pandocBin,
        $includeExecutableNativeAstParity
    ))->report();
    if ($json) {
        fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) . PHP_EOL);
    } else {
        fwrite(STDOUT, EpubUpstreamReaderEvidence::formatTextReport($report));
    }

    if (
        $requiredTestCount !== null
        && !EpubUpstreamReaderEvidence::hasRequiredMediaBagTestCount($report, $requiredTestCount)
    ) {
        fwrite(STDERR, "pandoc-epub-reader-evidence: media-bag test count did not match {$requiredTestCount}\n");
        exit(1);
    }

    if (
        $requiredFixtureReferenceCount !== null
        && !EpubUpstreamReaderEvidence::hasRequiredFixtureReferenceCount($report, $requiredFixtureReferenceCount)
    ) {
        fwrite(STDERR, "pandoc-epub-reader-evidence: fixture reference count did not match {$requiredFixtureReferenceCount}\n");
        exit(1);
    }

    if (
        $requiredExpectedMediaItemCount !== null
        && !EpubUpstreamReaderEvidence::hasRequiredExpectedMediaItemCount($report, $requiredExpectedMediaItemCount)
    ) {
        fwrite(STDERR, "pandoc-epub-reader-evidence: expected media item count did not match {$requiredExpectedMediaItemCount}\n");
        exit(1);
    }

    if ($requireNoValidationIssues && !EpubUpstreamReaderEvidence::hasNoValidationIssues($report)) {
        fwrite(STDERR, "pandoc-epub-reader-evidence: upstream EPUB reader denominator validation reported issues\n");
        exit(1);
    }

    if (
        $requireReferencedFixtureIdentity
        && !EpubUpstreamReaderEvidence::hasRequiredReferencedFixtureIdentity($report)
    ) {
        fwrite(
            STDERR,
            "pandoc-epub-reader-evidence: checked-in current EPUB reader referenced fixture identity did not match the expected snapshot\n"
            . "hint: use --checked-in-fixtures to gate the checked-in current reader evidence snapshot\n"
        );
        exit(1);
    }

    if ($requireStaticCurrentSignature && !EpubUpstreamReaderEvidence::hasRequiredStaticCurrentSignature($report)) {
        fwrite(
            STDERR,
            "pandoc-epub-reader-evidence: checked-in current EPUB reader static signature did not match the expected snapshot\n"
            . "hint: use --checked-in-fixtures to gate the checked-in current reader evidence snapshot\n"
        );
        exit(1);
    }

    if ($requireNativeAstPackageParity && !EpubUpstreamReaderEvidence::hasRequiredNativeAstPackageParity($report)) {
        fwrite(
            STDERR,
            "pandoc-epub-reader-evidence: checked-in current EPUB native/package parity did not match the expected snapshot\n"
            . "hint: run tools/pandoc-epub-native-ast-package.php --checked-in-fixtures summary --require-package-parity=71 --require-native-readiness=71 --require-mapped-parity=71 --require-fixture-identity --require-current-package-feature-coverage --require-current-package-feature-signature --require-current-native-ast-signature --require-runner-plan\n"
        );
        exit(1);
    }

    if ($requireRunnerNotRun && !EpubUpstreamReaderEvidence::hasRunnerNotRunEvidence($report)) {
        fwrite(STDERR, "pandoc-epub-reader-evidence: runner not-run evidence is invalid\n");
        exit(1);
    }

    if ($requireRunnerPlan && !EpubUpstreamReaderEvidence::hasRunnerPlanEvidence($report)) {
        fwrite(STDERR, "pandoc-epub-reader-evidence: runner command-plan evidence is invalid\n");
        exit(1);
    }

    if (
        $requireExecutableNativeAstParity
        && !EpubUpstreamReaderEvidence::hasRequiredExecutableNativeAstParity($report, $requiredPandocVersion)
    ) {
        fwrite(
            STDERR,
            "pandoc-epub-reader-evidence: checked-in current EPUB executable/native AST parity did not match the expected snapshot\n"
            . "hint: use --checked-in-fixtures --require-executable-native-ast-parity with a local pandoc 3.10 executable, optionally via --pandoc-bin=PATH --require-pandoc-version='pandoc 3.10'\n"
        );
        exit(1);
    }

    if (
        $requiredPandocVersion !== null
        && !$requireExecutableNativeAstParity
        && !EpubUpstreamReaderEvidence::hasRequiredExecutableNativeAstParity($report, $requiredPandocVersion)
    ) {
        fwrite(STDERR, "pandoc-epub-reader-evidence: pandoc executable version did not match {$requiredPandocVersion}\n");
        exit(1);
    }

    if ($requireRunnerResultArtifact && !EpubUpstreamReaderEvidence::hasRunnerResultArtifactEvidence($report)) {
        fwrite(STDERR, "pandoc-epub-reader-evidence: runner result artifact evidence is invalid\n");
        exit(1);
    }

    exit(0);
} catch (InvalidArgumentException $exception) {
    fwrite(STDERR, 'pandoc-epub-reader-evidence: ' . $exception->getMessage() . PHP_EOL);
    fwrite(STDERR, $usage() . PHP_EOL);
    exit(2);
} catch (Throwable $throwable) {
    fwrite(STDERR, 'pandoc-epub-reader-evidence: ' . $throwable::class . ': ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}
