<?php

declare(strict_types=1);

use PortLibs\Pandoc\PptxExecutableNativeAstComparisonHarness;

require __DIR__ . '/bootstrap.php';

$repoRoot = dirname(__DIR__);
$pptxDirectory = getenv('PANDOC_UPSTREAM_PPTX_DIR') ?: getenv('PANDOC_PPTX_NATIVE_AST_DIR') ?: $repoRoot . '/.upstream-cache/pandoc-current/test/pptx-reader';
$checkedInPptxDirectory = $repoRoot . '/lanes/pandoc/fixtures/upstream-current-pptx-reader';
$pptxDirectoryArgumentWasProvided = false;
$useCheckedInFixtures = false;
$pandocBin = getenv('PANDOC_BIN') ?: null;
$limit = 0;
$requiredExecutableParity = null;
$json = false;
$summary = false;

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help' || $argument === '-h') {
        fwrite(STDOUT, <<<'TXT'
Usage: php tools/pandoc-pptx-executable-native-ast.php [--pptx-dir=PATH|--checked-in-fixtures] [--pandoc-bin=PATH] [--limit=N] [--json] [--require-executable-parity=N] [summary]

Runs a local pandoc executable as `pandoc -f pptx -t native FILE.pptx`
and compares that native output with the local PHP PPTX reader by normalized
AST shape. Missing pandoc is reported as skipped with exit 0 unless executable
parity is required.
Use --checked-in-fixtures for the checked-in current PPTX fixture snapshot at
lanes/pandoc/fixtures/upstream-current-pptx-reader.

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

    if (str_starts_with($argument, '--pptx-dir=')) {
        $pptxDirectory = substr($argument, strlen('--pptx-dir='));
        $pptxDirectoryArgumentWasProvided = true;
        continue;
    }

    if ($argument === '--checked-in-fixtures') {
        $useCheckedInFixtures = true;
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

    fwrite(STDERR, "Unknown argument: {$argument}\n");
    exit(2);
}

if ($useCheckedInFixtures && $pptxDirectoryArgumentWasProvided) {
    fwrite(STDERR, "--checked-in-fixtures cannot be combined with --pptx-dir\n");
    exit(2);
}

if ($useCheckedInFixtures) {
    $pptxDirectory = $checkedInPptxDirectory;
}

if ($pptxDirectory !== '' && !str_starts_with($pptxDirectory, DIRECTORY_SEPARATOR)) {
    $pptxDirectory = $repoRoot . DIRECTORY_SEPARATOR . $pptxDirectory;
}

$harness = new PptxExecutableNativeAstComparisonHarness();
$report = $harness->run($pptxDirectory, [
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
        'pptxDirectory',
        'pandocExecutable',
        'pandocVersion',
        'totalPptxCount',
        'comparedPptxCount',
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
    && !PptxExecutableNativeAstComparisonHarness::hasRequiredExecutableParity($report, $requiredExecutableParity)
) {
    fwrite(
        STDERR,
        "pandoc-pptx-executable-native-ast: executable normalized AST parity did not match {$requiredExecutableParity}/{$requiredExecutableParity} PPTX files\n"
    );
    exit(1);
}

exit(0);
