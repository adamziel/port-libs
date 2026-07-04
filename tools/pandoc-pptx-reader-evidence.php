#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use PortLibs\Pandoc\PptxUpstreamReaderEvidence;

$usage = static function (): string {
    return <<<'TEXT'
Usage: php tools/pandoc-pptx-reader-evidence.php [options] [summary]

Options:
  --json                          Emit JSON instead of text.
  --repo-root PATH                Repository root. Defaults to the parent of tools/.
  --checked-in-fixtures           Use the checked-in current PPTX fixture snapshot
                                  without requiring an upstream checkout.
  --upstream-root PATH            Optional upstream Pandoc checkout root.
                                  Defaults to .upstream-cache/pandoc-current.
  --require-test-count N          Exit 1 unless Tests.Readers.Pptx has exactly N comparisons.
  --require-fixture-pair-count N  Exit 1 unless test/pptx-reader has exactly N PPTX/native pairs.
  --require-no-validation-issues  Exit 1 when denominator validation reports any issue.
  --require-static-current-evidence
                                  Exit 1 unless the pinned static Tests.Readers.Pptx
                                  denominator matches the checked-in current
                                  PPTX/native fixture snapshot.
  --require-static-native-mapped-parity
                                  Exit 1 unless the checked-in current PPTX/native
                                  snapshot matches by normalized AST shape.
  --require-static-executable-native-ast-parity
                                  Exit 1 unless the checked-in executable Pandoc
                                  native AST snapshot validates all 47 pairs.
  --require-runner-not-run        Exit 1 unless upstream runner evidence is structured as not-run.
  --require-runner-plan           Exit 1 unless upstream runner evidence includes the pinned
                                  planned-not-run command plan.
  --runner-result-artifact PATH   Validate a captured upstream runner result JSON artifact
                                  and its transcript file identities.
  --require-runner-result-artifact
                                  Exit 1 unless the supplied runner result artifact is valid.
  --help                          Show this help.

This is a denominator/evidence gate for the upstream PPTX reader fixture set.
It does not run Cabal/Tasty, execute pandoc, or claim writer parity.
TEXT;
};

$summaryReport = static function (array $report): array {
    $denominator = is_array($report['denominator'] ?? null) ? $report['denominator'] : [];
    $staticEvidence = is_array($report['staticCurrentEvidence'] ?? null) ? $report['staticCurrentEvidence'] : [];
    $staticDenominator = is_array($staticEvidence['readerDenominator'] ?? null) ? $staticEvidence['readerDenominator'] : [];
    $staticNativeParity = is_array($staticEvidence['nativeAstMappedParity'] ?? null) ? $staticEvidence['nativeAstMappedParity'] : [];
    $staticExecutableParity = is_array($staticEvidence['executableNativeAstMappedParity'] ?? null) ? $staticEvidence['executableNativeAstMappedParity'] : [];

    return [
        'schemaVersion' => $report['schemaVersion'] ?? null,
        'tool' => $report['tool'] ?? null,
        'status' => $report['status'] ?? null,
        'upstream' => $report['upstream'] ?? [],
        'denominator' => [
            'readerTestCompareCount' => $denominator['readerTestCompareCount'] ?? 0,
            'fixturePairCount' => $denominator['fixturePairCount'] ?? 0,
            'referencedPairCount' => $denominator['referencedPairCount'] ?? 0,
            'unpairedPptxFixtureCount' => $denominator['unpairedPptxFixtureCount'] ?? 0,
            'unpairedNativeFixtureCount' => $denominator['unpairedNativeFixtureCount'] ?? 0,
            'missingReferencedFileCount' => count(is_array($denominator['missingReferencedFiles'] ?? null) ? $denominator['missingReferencedFiles'] : []),
            'unreferencedFixturePairCount' => count(is_array($denominator['unreferencedFixturePairs'] ?? null) ? $denominator['unreferencedFixturePairs'] : []),
        ],
        'staticCurrentEvidence' => [
            'kind' => $staticEvidence['kind'] ?? null,
            'readerDenominator' => [
                'expectedCompareCount' => $staticDenominator['expectedCompareCount'] ?? 0,
            ],
            'checkedInFixturePairCount' => $staticEvidence['checkedInFixturePairCount'] ?? 0,
            'checkedInUnpairedPptxFixtureCount' => $staticEvidence['checkedInUnpairedPptxFixtureCount'] ?? 0,
            'checkedInUnpairedNativeFixtureCount' => $staticEvidence['checkedInUnpairedNativeFixtureCount'] ?? 0,
            'nativeAstMappedParity' => [
                'kind' => $staticNativeParity['kind'] ?? null,
                'status' => $staticNativeParity['status'] ?? null,
                'skipped' => $staticNativeParity['skipped'] ?? null,
                'requiredPairCount' => $staticNativeParity['requiredPairCount'] ?? 0,
                'totalPairCount' => $staticNativeParity['totalPairCount'] ?? 0,
                'comparedPairCount' => $staticNativeParity['comparedPairCount'] ?? 0,
                'bothParsedCount' => $staticNativeParity['bothParsedCount'] ?? 0,
                'parseFailureCount' => $staticNativeParity['parseFailureCount'] ?? 0,
                'normalizedAstMatchCount' => $staticNativeParity['normalizedAstMatchCount'] ?? 0,
                'normalizedAstMismatchCount' => $staticNativeParity['normalizedAstMismatchCount'] ?? 0,
                'normalizedAstMatchPercent' => $staticNativeParity['normalizedAstMatchPercent'] ?? null,
                'astParityStatus' => $staticNativeParity['astParityStatus'] ?? null,
                'hasRequiredMappedParity' => $staticNativeParity['hasRequiredMappedParity'] ?? false,
            ],
            'executableNativeAstMappedParity' => [
                'kind' => $staticExecutableParity['kind'] ?? null,
                'status' => $staticExecutableParity['status'] ?? null,
                'skipped' => $staticExecutableParity['skipped'] ?? null,
                'requiredPptxCount' => $staticExecutableParity['requiredPptxCount'] ?? 0,
                'snapshotFile' => $staticExecutableParity['snapshotFile'] ?? [],
                'capturedDate' => $staticExecutableParity['capturedDate'] ?? null,
                'requiredPandocVersion' => $staticExecutableParity['requiredPandocVersion'] ?? null,
                'pandocVersion' => $staticExecutableParity['pandocVersion'] ?? null,
                'totalPptxCount' => $staticExecutableParity['totalPptxCount'] ?? 0,
                'comparedPptxCount' => $staticExecutableParity['comparedPptxCount'] ?? 0,
                'localParsedCount' => $staticExecutableParity['localParsedCount'] ?? 0,
                'pandocParsedCount' => $staticExecutableParity['pandocParsedCount'] ?? 0,
                'nativeFixtureParsedCount' => $staticExecutableParity['nativeFixtureParsedCount'] ?? 0,
                'bothParsedCount' => $staticExecutableParity['bothParsedCount'] ?? 0,
                'parseFailureCount' => $staticExecutableParity['parseFailureCount'] ?? 0,
                'normalizedAstMatchCount' => $staticExecutableParity['normalizedAstMatchCount'] ?? 0,
                'normalizedAstMismatchCount' => $staticExecutableParity['normalizedAstMismatchCount'] ?? 0,
                'pandocNativeFixtureComparedCount' => $staticExecutableParity['pandocNativeFixtureComparedCount'] ?? 0,
                'pandocNativeFixtureMatchCount' => $staticExecutableParity['pandocNativeFixtureMatchCount'] ?? 0,
                'pandocNativeFixtureMismatchCount' => $staticExecutableParity['pandocNativeFixtureMismatchCount'] ?? 0,
                'astParityStatus' => $staticExecutableParity['astParityStatus'] ?? null,
                'hasRequiredExecutableParity' => $staticExecutableParity['hasRequiredExecutableParity'] ?? false,
                'hasRequiredPandocVersion' => $staticExecutableParity['hasRequiredPandocVersion'] ?? false,
                'validation' => $staticExecutableParity['validation'] ?? [],
            ],
            'validation' => $staticEvidence['validation'] ?? [],
        ],
        'runnerEvidence' => $report['runnerEvidence'] ?? [],
        'validation' => $report['validation'] ?? [],
    ];
};

try {
    $repoRoot = dirname(__DIR__);
    $upstreamRoot = PptxUpstreamReaderEvidence::DEFAULT_RELATIVE_UPSTREAM_ROOT;
    $useCheckedInFixtures = false;
    $upstreamRootArgumentWasProvided = false;
    $json = false;
    $requiredTestCount = null;
    $requiredFixturePairCount = null;
    $requireNoValidationIssues = false;
    $requireStaticCurrentEvidence = false;
    $requireStaticNativeMappedParity = false;
    $requireStaticExecutableNativeAstParity = false;
    $requireRunnerNotRun = false;
    $requireRunnerPlan = false;
    $requireRunnerResultArtifact = false;
    $runnerResultArtifact = null;
    $summary = false;
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
        if ($arg === 'summary') {
            $summary = true;
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
        if ($arg === '--require-static-native-mapped-parity') {
            $requireStaticNativeMappedParity = true;
            continue;
        }
        if ($arg === '--require-static-executable-native-ast-parity') {
            $requireStaticExecutableNativeAstParity = true;
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
        if ($arg === '--runner-result-artifact') {
            $runnerResultArtifact = $nextValue('--runner-result-artifact');
            continue;
        }
        if (str_starts_with($arg, '--runner-result-artifact=')) {
            $runnerResultArtifact = substr($arg, strlen('--runner-result-artifact='));
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

    if ($useCheckedInFixtures && $upstreamRootArgumentWasProvided) {
        throw new InvalidArgumentException('--checked-in-fixtures cannot be combined with --upstream-root');
    }

    if ($useCheckedInFixtures) {
        $upstreamRoot = 'missing-upstream-root-for-static-pptx-gate';
    }

    $report = (new PptxUpstreamReaderEvidence($repoRoot, $upstreamRoot, $runnerResultArtifact))->report();
    if ($summary) {
        $report = $summaryReport($report);
    }
    if ($json) {
        fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) . PHP_EOL);
    } else {
        fwrite(STDOUT, PptxUpstreamReaderEvidence::formatTextReport($report));
    }

    if (
        $requiredTestCount !== null
        && !PptxUpstreamReaderEvidence::hasRequiredReaderTestCount($report, $requiredTestCount)
    ) {
        fwrite(STDERR, "pandoc-pptx-reader-evidence: reader test comparison count did not match {$requiredTestCount}\n");
        exit(1);
    }

    if (
        $requiredFixturePairCount !== null
        && !PptxUpstreamReaderEvidence::hasRequiredFixturePairCount($report, $requiredFixturePairCount)
    ) {
        fwrite(STDERR, "pandoc-pptx-reader-evidence: fixture pair count did not match {$requiredFixturePairCount}\n");
        exit(1);
    }

    if ($requireNoValidationIssues && !PptxUpstreamReaderEvidence::hasNoValidationIssues($report)) {
        fwrite(STDERR, "pandoc-pptx-reader-evidence: upstream PPTX reader denominator validation reported issues\n");
        exit(1);
    }

    if ($requireStaticCurrentEvidence && !PptxUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($report)) {
        fwrite(STDERR, "pandoc-pptx-reader-evidence: checked-in current PPTX reader fixture evidence did not match the pinned static snapshot\n");
        exit(1);
    }

    if ($requireStaticNativeMappedParity && !PptxUpstreamReaderEvidence::hasRequiredStaticNativeMappedParity($report)) {
        fwrite(STDERR, "pandoc-pptx-reader-evidence: checked-in current PPTX/native mapped AST parity did not match the pinned static snapshot\n");
        exit(1);
    }

    if (
        $requireStaticExecutableNativeAstParity
        && !PptxUpstreamReaderEvidence::hasRequiredStaticExecutableNativeAstParity($report)
    ) {
        fwrite(STDERR, "pandoc-pptx-reader-evidence: checked-in current executable PPTX/native AST parity did not match the pinned static snapshot\n");
        exit(1);
    }

    if ($requireRunnerNotRun && !PptxUpstreamReaderEvidence::hasRunnerNotRunEvidence($report)) {
        fwrite(STDERR, "pandoc-pptx-reader-evidence: runner not-run evidence is invalid\n");
        exit(1);
    }

    if ($requireRunnerPlan && !PptxUpstreamReaderEvidence::hasRunnerPlanEvidence($report)) {
        fwrite(STDERR, "pandoc-pptx-reader-evidence: runner command-plan evidence is invalid\n");
        exit(1);
    }

    if ($requireRunnerResultArtifact && !PptxUpstreamReaderEvidence::hasRunnerResultArtifactEvidence($report)) {
        fwrite(STDERR, "pandoc-pptx-reader-evidence: runner result artifact evidence is invalid\n");
        exit(1);
    }

    exit(0);
} catch (InvalidArgumentException $exception) {
    fwrite(STDERR, 'pandoc-pptx-reader-evidence: ' . $exception->getMessage() . PHP_EOL);
    fwrite(STDERR, $usage() . PHP_EOL);
    exit(2);
} catch (Throwable $throwable) {
    fwrite(STDERR, 'pandoc-pptx-reader-evidence: ' . $throwable::class . ': ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}
