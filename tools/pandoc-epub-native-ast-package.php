<?php

declare(strict_types=1);

use PortLibs\Pandoc\EpubNativeAstPackageComparisonHarness;

require __DIR__ . '/bootstrap.php';

$repoRoot = dirname(__DIR__);
$epubDirectory = getenv('PANDOC_UPSTREAM_EPUB_DIR') ?: $repoRoot . '/.upstream-cache/pandoc-current/test/epub';
$limit = 0;
$requiredPackageParity = null;
$requiredNativeReadiness = null;
$requiredMappedParity = null;
$json = false;
$summary = false;

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help' || $argument === '-h') {
        fwrite(STDOUT, <<<'TXT'
Usage: php tools/pandoc-epub-native-ast-package.php [--epub-dir=PATH] [--limit=N] [--json] [summary] [gates]

Compares local PHP EPUB package parsing and reader output against upstream EPUB
fixtures. Same-basename .native fixtures are parsed and compared by normalized
AST shape. Package coverage/readiness and strict AST equality are gated
separately so current gaps are visible rather than hidden.

Gates:
  --require-package-parity=N       Require exactly N EPUB packages parsed by package and reader paths.
  --require-native-readiness=N     Require exactly N EPUB/native pairs parsed by both readers.
  --require-mapped-parity=N        Require exactly N EPUB/native pairs with normalized AST equality.

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

    if (str_starts_with($argument, '--epub-dir=')) {
        $epubDirectory = substr($argument, strlen('--epub-dir='));
        continue;
    }

    if (str_starts_with($argument, '--limit=')) {
        $limit = max(0, (int) substr($argument, strlen('--limit=')));
        continue;
    }

    if (str_starts_with($argument, '--require-package-parity=')) {
        $requiredPackageParity = parseNonNegativeInt($argument, '--require-package-parity=');
        continue;
    }

    if (str_starts_with($argument, '--require-native-readiness=')) {
        $requiredNativeReadiness = parseNonNegativeInt($argument, '--require-native-readiness=');
        continue;
    }

    if (str_starts_with($argument, '--require-mapped-parity=')) {
        $requiredMappedParity = parseNonNegativeInt($argument, '--require-mapped-parity=');
        continue;
    }

    fwrite(STDERR, "Unknown argument: {$argument}\n");
    exit(2);
}

if ($epubDirectory !== '' && !str_starts_with($epubDirectory, DIRECTORY_SEPARATOR)) {
    $epubDirectory = $repoRoot . DIRECTORY_SEPARATOR . $epubDirectory;
}

$harness = new EpubNativeAstPackageComparisonHarness();
$report = $harness->run($epubDirectory, ['limit' => $limit]);

if ($summary) {
    $report = array_intersect_key($report, array_flip([
        'schemaVersion',
        'tool',
        'status',
        'skipped',
        'reason',
        'verdict',
        'evidenceKind',
        'upstreamEpubDirectory',
        'totalEpubCount',
        'comparedEpubCount',
        'packageParsedCount',
        'readerParsedCount',
        'packageParseFailureCount',
        'readerParseFailureCount',
        'packageAcceptanceStatus',
        'totalPairCount',
        'comparedPairCount',
        'epubPairParsedCount',
        'nativeParsedCount',
        'bothParsedCount',
        'astParseFailureCount',
        'nativeParseFailureCount',
        'normalizedAstMatchCount',
        'normalizedAstMismatchCount',
        'normalizedAstMatchPercent',
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
    $requiredPackageParity !== null
    && !EpubNativeAstPackageComparisonHarness::hasRequiredPackageParity($report, $requiredPackageParity)
) {
    fwrite(STDERR, "pandoc-epub-native-ast-package: package parity did not match {$requiredPackageParity}/{$requiredPackageParity} EPUB files\n");
    exit(1);
}

if (
    $requiredNativeReadiness !== null
    && !EpubNativeAstPackageComparisonHarness::hasRequiredNativeReadiness($report, $requiredNativeReadiness)
) {
    fwrite(STDERR, "pandoc-epub-native-ast-package: native readiness did not match {$requiredNativeReadiness}/{$requiredNativeReadiness} EPUB/native pairs\n");
    exit(1);
}

if (
    $requiredMappedParity !== null
    && !EpubNativeAstPackageComparisonHarness::hasRequiredMappedParity($report, $requiredMappedParity)
) {
    fwrite(STDERR, "pandoc-epub-native-ast-package: normalized AST mapped parity did not match {$requiredMappedParity}/{$requiredMappedParity} EPUB/native pairs\n");
    exit(1);
}

exit(0);

function parseNonNegativeInt(string $argument, string $prefix): int
{
    $raw = substr($argument, strlen($prefix));
    if (!ctype_digit($raw)) {
        fwrite(STDERR, "{$prefix} requires a non-negative integer\n");
        exit(2);
    }

    return (int) $raw;
}
