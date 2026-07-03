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
$json = false;
$summary = false;

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help' || $argument === '-h') {
        fwrite(STDOUT, <<<'TXT'
Usage: php tools/pandoc-epub-media-bag.php [--upstream-root=PATH|--checked-in-fixtures] [--fixture-base=PATH] [--limit=N] [--json] [--require-media-bag-parity=N] [--require-media-bag-item-count=N] [--require-current-media-bag-signatures] [summary]

Compares local PHP EPUB reader media-bag output with upstream Tests.Readers.EPUB
expectations by normalized path, MIME type, and byte size when the upstream cache
is present. Missing cache is reported as skipped with exit 0 unless required
parity is requested.
Use --checked-in-fixtures for the checked-in current EPUB reader fixture snapshot
at lanes/pandoc/fixtures/upstream-current-epub-reader.
With --require-media-bag-parity=N, exits 1 unless exactly N EPUB fixtures are
compared, parsed, and matched by normalized media-bag tuples.
With --require-media-bag-item-count=N, exits 1 unless both upstream expectations
and local extraction report exactly N media-bag items.
With --require-current-media-bag-signatures, exits 1 unless the checked-in
current fixture snapshot has the exact per-fixture media-bag signatures.

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

    fwrite(STDERR, "Unknown argument: {$argument}\n");
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

exit(0);
