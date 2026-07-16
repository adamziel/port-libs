#!/usr/bin/env php
<?php

declare(strict_types=1);

use PortLibs\Pandoc\EpubMediaBagComparisonHarness;

require __DIR__ . '/bootstrap.php';

$repoRoot = dirname(__DIR__);
$checkedInFixtureRoot = 'lanes/pandoc/fixtures/upstream-current-epub-reader';
$upstreamRoot = getenv('PANDOC_UPSTREAM_ROOT') ?: getenv('PANDOC_EPUB_UPSTREAM_ROOT') ?: $repoRoot . '/.upstream-cache/pandoc-current';
$fixtureBase = getenv('PANDOC_EPUB_FIXTURE_BASE') ?: null;
$useCheckedInFixtures = false;
$upstreamRootArgumentWasProvided = false;
$fixtureBaseArgumentWasProvided = false;
$limit = 0;
$requiredMediaBagParity = null;
$requiredMediaBagItemCount = null;
$requireCurrentMediaBagSignatures = false;
$requireRunnerPlan = false;
$runnerResultArtifact = null;
$requireRunnerResultArtifact = false;
$json = false;
$summary = false;

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help' || $argument === '-h') {
        fwrite(STDOUT, <<<'TXT'
Usage: php tools/pandoc-epub-media-bag.php [--repo-root=PATH] [--upstream-root=PATH|--checked-in-fixtures] [--fixture-base=PATH] [--limit=N] [--json] [--require-media-bag-parity=N] [--require-media-bag-item-count=N] [--require-current-media-bag-signatures] [--require-runner-plan] [--runner-result-artifact=PATH] [--require-runner-result-artifact] [summary]

Compares local PHP EPUB reader media-bag output with upstream Tests.Readers.EPUB
expectations by normalized path, MIME type, and byte size when the upstream cache
is present. Missing cache is reported as skipped with exit 0 unless required
parity is requested.
Use --repo-root=PATH to resolve relative fixture, runner artifact, and transcript
paths. It defaults to the parent of tools/.
Use --checked-in-fixtures for the checked-in current EPUB reader fixture snapshot
at lanes/pandoc/fixtures/upstream-current-epub-reader.
With --require-media-bag-parity=N, exits 1 unless exactly N EPUB fixtures are
compared, parsed, and matched by normalized media-bag tuples.
With --require-media-bag-item-count=N, exits 1 unless both upstream expectations
and local extraction report exactly N media-bag items.
With --require-current-media-bag-signatures, exits 1 unless the checked-in
current fixture snapshot has the exact per-fixture media-bag signatures.
With --require-runner-plan, exits 1 unless structured not-run upstream
Tests.Readers.EPUB media-bag runner command-plan evidence is present.
With --runner-result-artifact=PATH, validates a captured upstream runner result
JSON artifact against the pinned Tests.Readers.EPUB media-bag target.
With --require-runner-result-artifact, exits 1 unless the supplied runner result
artifact is valid.

TXT);
        exit(0);
    }

    if ($argument === '--json') {
        $json = true;
        continue;
    }

    if ($argument === 'summary') {
        $summary = true;
        continue;
    }

    if ($argument === '--checked-in-fixtures') {
        $useCheckedInFixtures = true;
        continue;
    }

    if (str_starts_with($argument, '--upstream-root=')) {
        $upstreamRoot = substr($argument, strlen('--upstream-root='));
        $upstreamRootArgumentWasProvided = true;
        continue;
    }

    if (str_starts_with($argument, '--repo-root=')) {
        $repoRoot = rtrim(substr($argument, strlen('--repo-root=')), DIRECTORY_SEPARATOR);
        continue;
    }

    if (str_starts_with($argument, '--fixture-base=')) {
        $fixtureBase = substr($argument, strlen('--fixture-base='));
        $fixtureBaseArgumentWasProvided = true;
        continue;
    }

    if (str_starts_with($argument, '--limit=')) {
        $limit = max(0, (int) substr($argument, strlen('--limit=')));
        continue;
    }

    if (str_starts_with($argument, '--require-media-bag-parity=')) {
        $rawCount = substr($argument, strlen('--require-media-bag-parity='));
        if (!ctype_digit($rawCount)) {
            fwrite(STDERR, "--require-media-bag-parity must be a non-negative integer\n");
            exit(2);
        }
        $requiredMediaBagParity = (int) $rawCount;
        continue;
    }

    if (str_starts_with($argument, '--require-media-bag-item-count=')) {
        $rawCount = substr($argument, strlen('--require-media-bag-item-count='));
        if (!ctype_digit($rawCount)) {
            fwrite(STDERR, "--require-media-bag-item-count must be a non-negative integer\n");
            exit(2);
        }
        $requiredMediaBagItemCount = (int) $rawCount;
        continue;
    }

    if ($argument === '--require-current-media-bag-signatures') {
        $requireCurrentMediaBagSignatures = true;
        continue;
    }

    if ($argument === '--require-runner-plan') {
        $requireRunnerPlan = true;
        continue;
    }

    if ($argument === '--require-runner-result-artifact') {
        $requireRunnerResultArtifact = true;
        continue;
    }

    if (str_starts_with($argument, '--runner-result-artifact=')) {
        $runnerResultArtifact = substr($argument, strlen('--runner-result-artifact='));
        continue;
    }

    fwrite(STDERR, "Unknown argument: {$argument}\n");
    exit(2);
}

if ($runnerResultArtifact === '') {
    fwrite(STDERR, "--runner-result-artifact must not be empty\n");
    exit(2);
}
if ($repoRoot === '') {
    fwrite(STDERR, "--repo-root must not be empty\n");
    exit(2);
}

if ($useCheckedInFixtures && ($upstreamRootArgumentWasProvided || $fixtureBaseArgumentWasProvided)) {
    fwrite(STDERR, "--checked-in-fixtures cannot be combined with --upstream-root or --fixture-base\n");
    exit(2);
}

if (
    $useCheckedInFixtures
    || ($requireCurrentMediaBagSignatures && !$upstreamRootArgumentWasProvided && !$fixtureBaseArgumentWasProvided)
) {
    $upstreamRoot = $checkedInFixtureRoot;
    $fixtureBase = $checkedInFixtureRoot;
}

if ($upstreamRoot !== '' && !str_starts_with($upstreamRoot, DIRECTORY_SEPARATOR)) {
    $upstreamRoot = $repoRoot . DIRECTORY_SEPARATOR . $upstreamRoot;
}
if (is_string($fixtureBase) && $fixtureBase !== '' && !str_starts_with($fixtureBase, DIRECTORY_SEPARATOR)) {
    $fixtureBase = $repoRoot . DIRECTORY_SEPARATOR . $fixtureBase;
}

$harness = new EpubMediaBagComparisonHarness();
$options = ['limit' => $limit];
if (is_string($fixtureBase) && $fixtureBase !== '') {
    $options['fixtureBase'] = $fixtureBase;
}
if (is_string($runnerResultArtifact)) {
    $options['repoRoot'] = $repoRoot;
    $options['runnerResultArtifact'] = $runnerResultArtifact;
}
$report = $harness->run($upstreamRoot, $options);

if ($summary) {
    $report = array_intersect_key($report, array_flip([
        'schemaVersion',
        'tool',
        'status',
        'skipped',
        'reason',
        'verdict',
        'evidenceKind',
        'upstreamRoot',
        'fixtureBase',
        'runnerEvidence',
        'totalCaseCount',
        'comparedCaseCount',
        'epubParsedCount',
        'parseFailureCount',
        'expectedMediaItemCount',
        'actualMediaItemCount',
        'mediaBagMatchCount',
        'mediaBagMismatchCount',
        'mediaBagMatchPercent',
        'mediaBagParityStatus',
        'currentMediaBagSignature',
        'mediaBagSignatures',
        'orderedRemainingGaps',
    ]));
}

if ($json) {
    fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
} else {
    fwrite(STDOUT, $harness->formatReport($report));
}

if (
    $requiredMediaBagParity !== null
    && !EpubMediaBagComparisonHarness::hasRequiredMediaBagParity($report, $requiredMediaBagParity)
) {
    fwrite(
        STDERR,
        "pandoc-epub-media-bag: normalized media-bag parity did not match {$requiredMediaBagParity}/{$requiredMediaBagParity} EPUB fixtures\n"
    );
    exit(1);
}

if (
    $requiredMediaBagItemCount !== null
    && !EpubMediaBagComparisonHarness::hasRequiredMediaBagItemCount($report, $requiredMediaBagItemCount)
) {
    fwrite(
        STDERR,
        "pandoc-epub-media-bag: expected/actual media-bag item count did not match {$requiredMediaBagItemCount}\n"
    );
    exit(1);
}

if (
    $requireCurrentMediaBagSignatures
    && !EpubMediaBagComparisonHarness::hasRequiredCurrentMediaBagSignatures($report)
) {
    fwrite(
        STDERR,
        "pandoc-epub-media-bag: checked-in current EPUB media-bag signatures did not match the expected snapshot\n"
    );
    exit(1);
}

if (
    $requireRunnerPlan
    && !EpubMediaBagComparisonHarness::hasRunnerPlanEvidence($report)
) {
    fwrite(
        STDERR,
        "pandoc-epub-media-bag: upstream EPUB media-bag runner command-plan evidence is invalid\n"
    );
    exit(1);
}

if (
    $requireRunnerResultArtifact
    && !EpubMediaBagComparisonHarness::hasRunnerResultArtifactEvidence($report)
) {
    fwrite(
        STDERR,
        "pandoc-epub-media-bag: upstream EPUB media-bag runner result artifact evidence is invalid\n"
    );
    exit(1);
}

exit(0);
