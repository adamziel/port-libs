<?php

declare(strict_types=1);

use PortLibs\Pandoc\PptxNativeAstComparisonHarness;

require __DIR__ . '/bootstrap.php';

$repoRoot = dirname(__DIR__);
$defaultPptxDirectory = $repoRoot . '/.upstream-cache/pandoc-current/test/pptx-reader';
$pptxDirectory = getenv('PANDOC_UPSTREAM_PPTX_DIR') ?: getenv('PANDOC_PPTX_NATIVE_AST_DIR') ?: $defaultPptxDirectory;
$limit = 0;
$requiredMappedParity = null;
$json = false;
$summary = false;

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help' || $argument === '-h') {
        fwrite(STDOUT, <<<'TXT'
Usage: php tools/pandoc-pptx-native-ast.php [--upstream-pptx-dir=PATH] [--limit=N] [--json] [--require-mapped-parity=N] [summary]

Compares local PHP PPTX reader output with same-basename upstream .native
expectations by normalized AST shape when the upstream cache is present.
Missing cache is reported as skipped with exit 0 unless required parity is
requested.
With --require-mapped-parity=N, exits 1 unless exactly N paired fixtures are
compared, parsed by both readers, and matched by normalized AST shape.

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

    if (str_starts_with($argument, '--upstream-pptx-dir=')) {
        $pptxDirectory = substr($argument, strlen('--upstream-pptx-dir='));
        continue;
    }

    if (str_starts_with($argument, '--limit=')) {
        $limit = max(0, (int) substr($argument, strlen('--limit=')));
        continue;
    }

    if (str_starts_with($argument, '--require-mapped-parity=')) {
        $rawCount = substr($argument, strlen('--require-mapped-parity='));
        if (!ctype_digit($rawCount)) {
            fwrite(STDERR, "--require-mapped-parity must be a non-negative integer\n");
            exit(2);
        }
        $requiredMappedParity = (int) $rawCount;
        continue;
    }

    fwrite(STDERR, "Unknown argument: {$argument}\n");
    exit(2);
}

if ($pptxDirectory !== '' && !str_starts_with($pptxDirectory, DIRECTORY_SEPARATOR)) {
    $pptxDirectory = $repoRoot . DIRECTORY_SEPARATOR . $pptxDirectory;
}

$harness = new PptxNativeAstComparisonHarness();
$report = $harness->run($pptxDirectory, ['limit' => $limit]);

if ($summary) {
    $report = array_intersect_key($report, array_flip([
        'schemaVersion',
        'tool',
        'status',
        'skipped',
        'reason',
        'verdict',
        'evidenceKind',
        'upstreamPptxDirectory',
        'totalPairCount',
        'comparedPairCount',
        'pptxParsedCount',
        'nativeParsedCount',
        'bothParsedCount',
        'parseFailureCount',
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
    $requiredMappedParity !== null
    && !PptxNativeAstComparisonHarness::hasRequiredMappedParity($report, $requiredMappedParity)
) {
    fwrite(
        STDERR,
        "pandoc-pptx-native-ast: normalized AST mapped parity did not match {$requiredMappedParity}/{$requiredMappedParity} paired fixtures\n"
    );
    exit(1);
}

exit(0);
