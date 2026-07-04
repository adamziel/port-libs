<?php

declare(strict_types=1);

use PortLibs\Pandoc\EpubExecutableNativeAstComparisonHarness;

require __DIR__ . '/bootstrap.php';

$repoRoot = dirname(__DIR__);
$checkedInEpubDirectory = $repoRoot . '/lanes/pandoc/fixtures/upstream-current-epub-reader/epub';
$epubDirectory = getenv('PANDOC_UPSTREAM_EPUB_DIR') ?: $repoRoot . '/.upstream-cache/pandoc-current/test/epub';
$pandocBin = getenv('PANDOC_BIN') ?: null;
$limit = 0;
$requiredExecutableParity = null;
$requiredPandocVersion = null;
$json = false;
$summary = false;

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help' || $argument === '-h') {
        fwrite(STDOUT, <<<'TXT'
Usage: php tools/pandoc-epub-executable-native-ast.php [--epub-dir=PATH|--checked-in-fixtures] [--pandoc-bin=PATH] [--limit=N] [--json] [--require-executable-parity=N] [--require-pandoc-version=VERSION] [summary]

Runs a local pandoc executable as `pandoc -f epub -t native FILE.epub`
and compares that native output with the local PHP EPUB reader and paired
checked-in .native fixtures by normalized AST shape. Missing pandoc is reported
as skipped with exit 0 unless executable parity is required.

With --checked-in-fixtures, uses the checked-in current upstream EPUB fixture
snapshot under lanes/pandoc/fixtures/upstream-current-epub-reader/epub.

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
        $epubDirectory = $checkedInEpubDirectory;
        continue;
    }

    if (str_starts_with($argument, '--epub-dir=')) {
        $epubDirectory = substr($argument, strlen('--epub-dir='));
        continue;
    }

    if (str_starts_with($argument, '--pandoc-bin=')) {
        $pandocBin = substr($argument, strlen('--pandoc-bin='));
        continue;
    }

    if (str_starts_with($argument, '--limit=')) {
        $limit = max(0, (int) substr($argument, strlen('--limit=')));
        continue;
    }

    if (str_starts_with($argument, '--require-executable-parity=')) {
        $rawCount = substr($argument, strlen('--require-executable-parity='));
        if (!ctype_digit($rawCount)) {
            fwrite(STDERR, "--require-executable-parity must be a non-negative integer\n");
            exit(2);
        }
        $requiredExecutableParity = (int) $rawCount;
        continue;
    }

    if (str_starts_with($argument, '--require-pandoc-version=')) {
        $requiredPandocVersion = substr($argument, strlen('--require-pandoc-version='));
        if ($requiredPandocVersion === '') {
            fwrite(STDERR, "--require-pandoc-version must not be empty\n");
            exit(2);
        }
        continue;
    }

    fwrite(STDERR, "Unknown argument: {$argument}\n");
    exit(2);
}

if ($epubDirectory !== '' && !str_starts_with($epubDirectory, DIRECTORY_SEPARATOR)) {
    $epubDirectory = $repoRoot . DIRECTORY_SEPARATOR . $epubDirectory;
}

$harness = new EpubExecutableNativeAstComparisonHarness();
$report = $harness->run($epubDirectory, [
    'limit' => $limit,
    'pandocBin' => $pandocBin,
]);

if ($summary) {
    $report = array_intersect_key($report, array_flip([
        'schemaVersion',
        'tool',
        'status',
        'skipped',
        'reason',
        'verdict',
        'evidenceKind',
        'epubDirectory',
        'pandocExecutable',
        'pandocVersion',
        'totalEpubCount',
        'comparedEpubCount',
        'localParsedCount',
        'pandocParsedCount',
        'nativeFixtureParsedCount',
        'bothParsedCount',
        'parseFailureCount',
        'normalizedAstMatchCount',
        'normalizedAstMismatchCount',
        'normalizedAstMatchPercent',
        'pandocNativeFixtureComparedCount',
        'pandocNativeFixtureMatchCount',
        'pandocNativeFixtureMismatchCount',
        'pandocNativeFixtureMatchPercent',
        'pandocNativeFixtureByteComparedCount',
        'pandocNativeFixtureByteMatchCount',
        'pandocNativeFixtureByteMismatchCount',
        'pandocNativeFixtureByteMatchPercent',
        'astParityStatus',
        'mismatchCategories',
        'orderedRemainingGaps',
    ]));
}

if ($json) {
    fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
} else {
    fwrite(STDOUT, $harness->formatReport($report));
}

if (
    $requiredExecutableParity !== null
    && !EpubExecutableNativeAstComparisonHarness::hasRequiredExecutableParity($report, $requiredExecutableParity)
) {
    fwrite(
        STDERR,
        "pandoc-epub-executable-native-ast: executable normalized AST parity did not match {$requiredExecutableParity}/{$requiredExecutableParity} EPUB files\n"
    );
    exit(1);
}

if (
    $requiredPandocVersion !== null
    && !EpubExecutableNativeAstComparisonHarness::hasRequiredPandocVersion($report, $requiredPandocVersion)
) {
    fwrite(
        STDERR,
        "pandoc-epub-executable-native-ast: pandoc version did not match {$requiredPandocVersion}\n"
    );
    exit(1);
}

exit(0);
