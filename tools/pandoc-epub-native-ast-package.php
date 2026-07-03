<?php

declare(strict_types=1);

use PortLibs\Pandoc\EpubNativeAstPackageComparisonHarness;

require __DIR__ . '/bootstrap.php';

$repoRoot = dirname(__DIR__);
$checkedInEpubDirectory = $repoRoot . '/lanes/pandoc/fixtures/upstream-current-epub-reader/epub';
$environmentEpubDirectory = getenv('PANDOC_UPSTREAM_EPUB_DIR');
$epubDirectory = is_string($environmentEpubDirectory) && $environmentEpubDirectory !== ''
    ? $environmentEpubDirectory
    : $repoRoot . '/.upstream-cache/pandoc-current/test/epub';
$epubDirectoryWasExplicit = is_string($environmentEpubDirectory) && $environmentEpubDirectory !== '';
$epubDirectoryArgumentWasProvided = false;
$useCheckedInFixtures = false;
$limit = 0;
$requiredPackageParity = null;
$requiredNativeReadiness = null;
$requiredMappedParity = null;
$requireFixtureIdentity = false;
$requireCurrentPackageFeatureCoverage = false;
$requireCurrentPackageFeatureSignature = false;
$requireCurrentNativeAstSignature = false;
$json = false;
$summary = false;

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help' || $argument === '-h') {
        fwrite(STDOUT, <<<'TXT'
Usage: php tools/pandoc-epub-native-ast-package.php [--epub-dir=PATH|--checked-in-fixtures] [--limit=N] [--json] [summary] [gates]

Compares local PHP EPUB package parsing and reader output against upstream EPUB
fixtures. Same-basename .native fixtures are parsed and compared by normalized
AST shape. Package coverage/readiness and normalized AST equality are gated
separately so current gaps are visible rather than hidden. By default the tool
uses .upstream-cache/pandoc-current/test/epub when available. Use
--checked-in-fixtures for the checked-in snapshot at
lanes/pandoc/fixtures/upstream-current-epub-reader/epub. When
--require-fixture-identity is used without --epub-dir or PANDOC_UPSTREAM_EPUB_DIR,
the checked-in snapshot is selected automatically.

Source:
  --epub-dir=PATH                  Read EPUB/.native fixtures from PATH.
  --checked-in-fixtures            Read the checked-in current EPUB fixture snapshot.

Gates:
  --require-package-parity=N       Require exactly N EPUB packages parsed by package and reader paths.
  --require-native-readiness=N     Require exactly N EPUB/native pairs parsed by both readers.
  --require-mapped-parity=N        Require exactly N EPUB/native pairs with normalized AST equality.
  --require-fixture-identity       Require the checked-in current EPUB fixture snapshot hashes and byte counts.
  --require-current-package-feature-coverage
                                   Require the checked-in current EPUB package feature coverage snapshot.
  --require-current-package-feature-signature
                                   Require the checked-in current EPUB fixture identity plus exact package feature signature.
  --require-current-native-ast-signature
                                   Require the checked-in current EPUB fixture identity plus exact normalized native AST signature.

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
        $epubDirectoryWasExplicit = true;
        $epubDirectoryArgumentWasProvided = true;
        continue;
    }

    if ($argument === '--checked-in-fixtures') {
        $useCheckedInFixtures = true;
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

    if ($argument === '--require-fixture-identity') {
        $requireFixtureIdentity = true;
        continue;
    }

    if ($argument === '--require-current-package-feature-coverage') {
        $requireCurrentPackageFeatureCoverage = true;
        continue;
    }

    if ($argument === '--require-current-package-feature-signature') {
        $requireCurrentPackageFeatureSignature = true;
        continue;
    }

    if ($argument === '--require-current-native-ast-signature') {
        $requireCurrentNativeAstSignature = true;
        continue;
    }

    fwrite(STDERR, "Unknown argument: {$argument}\n");
    exit(2);
}

if ($useCheckedInFixtures && $epubDirectoryArgumentWasProvided) {
    fwrite(STDERR, "--checked-in-fixtures cannot be combined with --epub-dir\n");
    exit(2);
}

if (
    $useCheckedInFixtures
    || (
        (
            $requireFixtureIdentity
            || $requireCurrentPackageFeatureCoverage
            || $requireCurrentPackageFeatureSignature
            || $requireCurrentNativeAstSignature
        )
        && !$epubDirectoryWasExplicit
    )
) {
    $epubDirectory = $checkedInEpubDirectory;
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
        'fixtureIdentity',
        'packageFeatureCoverage',
        'packageFeatureSignature',
        'currentNativeAstSignature',
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
    $requireFixtureIdentity
    && !EpubNativeAstPackageComparisonHarness::hasRequiredFixtureIdentity($report)
) {
    fwrite(
        STDERR,
        "pandoc-epub-native-ast-package: checked-in current EPUB fixture identity did not match the expected snapshot for {$epubDirectory}\n"
        . "hint: use --checked-in-fixtures or --epub-dir=lanes/pandoc/fixtures/upstream-current-epub-reader/epub to gate the checked-in snapshot\n"
    );
    exit(1);
}

if (
    $requireCurrentPackageFeatureCoverage
    && !EpubNativeAstPackageComparisonHarness::hasRequiredCurrentPackageFeatureCoverage($report)
) {
    fwrite(
        STDERR,
        "pandoc-epub-native-ast-package: checked-in current EPUB package feature coverage snapshot did not match the expected snapshot for {$epubDirectory}\n"
        . "hint: use --checked-in-fixtures or --epub-dir=lanes/pandoc/fixtures/upstream-current-epub-reader/epub to gate the checked-in snapshot\n"
    );
    exit(1);
}

if (
    $requireCurrentPackageFeatureSignature
    && !EpubNativeAstPackageComparisonHarness::hasRequiredCurrentPackageFeatureSignature($report)
) {
    fwrite(
        STDERR,
        "pandoc-epub-native-ast-package: checked-in current EPUB package feature signature did not match the expected snapshot for {$epubDirectory}\n"
        . "hint: use --checked-in-fixtures or --epub-dir=lanes/pandoc/fixtures/upstream-current-epub-reader/epub to gate the checked-in snapshot\n"
    );
    exit(1);
}

if (
    $requireCurrentNativeAstSignature
    && !EpubNativeAstPackageComparisonHarness::hasRequiredCurrentNativeAstSignature($report)
) {
    fwrite(
        STDERR,
        "pandoc-epub-native-ast-package: checked-in current EPUB normalized native AST signature did not match the expected snapshot for {$epubDirectory}\n"
        . "hint: use --checked-in-fixtures or --epub-dir=lanes/pandoc/fixtures/upstream-current-epub-reader/epub to gate the checked-in snapshot\n"
    );
    exit(1);
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
