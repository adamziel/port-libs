<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxNativeAstComparisonHarness;

require __DIR__ . '/bootstrap.php';

$repoRoot = dirname(__DIR__);
$defaultDocxDirectory = $repoRoot . '/.upstream-cache/pandoc-current/test/docx';
$docxDirectory = getenv('PANDOC_UPSTREAM_DOCX_DIR') ?: getenv('PANDOC_DOCX_NATIVE_AST_DIR') ?: $defaultDocxDirectory;
$limit = 0;
$requiredMappedParity = null;
$json = false;
$summary = false;

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help' || $argument === '-h') {
        fwrite(STDOUT, <<<'TXT'
Usage: php tools/pandoc-docx-native-ast.php [--upstream-docx-dir=PATH] [--limit=N] [--json] [--require-mapped-parity=N] [summary]

Compares local PHP DOCX reader output with same-basename upstream .native
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

    if (str_starts_with($argument, '--upstream-docx-dir=')) {
        $docxDirectory = substr($argument, strlen('--upstream-docx-dir='));
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

if ($docxDirectory !== '' && !str_starts_with($docxDirectory, DIRECTORY_SEPARATOR)) {
    $docxDirectory = $repoRoot . DIRECTORY_SEPARATOR . $docxDirectory;
}

$harness = new DocxNativeAstComparisonHarness();
$report = $harness->run($docxDirectory, ['limit' => $limit]);

if ($summary) {
    $report = array_intersect_key($report, array_flip([
        'schemaVersion',
        'tool',
        'status',
        'skipped',
        'reason',
        'verdict',
        'evidenceKind',
        'upstreamDocxDirectory',
        'totalPairCount',
        'comparedPairCount',
        'docxParsedCount',
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
    && !DocxNativeAstComparisonHarness::hasRequiredMappedParity($report, $requiredMappedParity)
) {
    fwrite(
        STDERR,
        "pandoc-docx-native-ast: normalized AST mapped parity did not match {$requiredMappedParity}/{$requiredMappedParity} paired fixtures\n"
    );
    exit(1);
}

exit(0);
