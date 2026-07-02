<?php

declare(strict_types=1);

use PortLibs\Pandoc\XlsxExecutableNativeAstComparisonHarness;

require __DIR__ . '/bootstrap.php';

$repoRoot = dirname(__DIR__);
$xlsxDirectory = getenv('PANDOC_UPSTREAM_XLSX_DIR') ?: getenv('PANDOC_XLSX_NATIVE_AST_DIR') ?: $repoRoot . '/.upstream-cache/pandoc-current/test/xlsx-reader';
$pandocBin = getenv('PANDOC_BIN') ?: null;
$limit = 0;
$requiredExecutableParity = null;
$json = false;
$summary = false;

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help' || $argument === '-h') {
        fwrite(STDOUT, <<<'TXT'
Usage: php tools/pandoc-xlsx-executable-native-ast.php [--xlsx-dir=PATH] [--pandoc-bin=PATH] [--limit=N] [--json] [--require-executable-parity=N] [summary]

Runs a local pandoc executable as `pandoc -f xlsx -t native FILE.xlsx`
and compares that native output with the local PHP XLSX reader by normalized
AST shape. Missing pandoc is reported as skipped with exit 0 unless executable
parity is required.

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

    if (str_starts_with($argument, '--xlsx-dir=')) {
        $xlsxDirectory = substr($argument, strlen('--xlsx-dir='));
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

if ($xlsxDirectory !== '' && !str_starts_with($xlsxDirectory, DIRECTORY_SEPARATOR)) {
    $xlsxDirectory = $repoRoot . DIRECTORY_SEPARATOR . $xlsxDirectory;
}

$harness = new XlsxExecutableNativeAstComparisonHarness();
$report = $harness->run($xlsxDirectory, [
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
        'xlsxDirectory',
        'pandocExecutable',
        'pandocVersion',
        'totalXlsxCount',
        'comparedXlsxCount',
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
    && !XlsxExecutableNativeAstComparisonHarness::hasRequiredExecutableParity($report, $requiredExecutableParity)
) {
    fwrite(
        STDERR,
        "pandoc-xlsx-executable-native-ast: executable normalized AST parity did not match {$requiredExecutableParity}/{$requiredExecutableParity} XLSX files\n"
    );
    exit(1);
}

exit(0);
