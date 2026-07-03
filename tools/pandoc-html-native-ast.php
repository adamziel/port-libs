<?php

declare(strict_types=1);

use PortLibs\Pandoc\HtmlNativeAstComparisonHarness;

require __DIR__ . '/bootstrap.php';

$repoRoot = dirname(__DIR__);
$defaultHtmlDirectory = $repoRoot . '/lanes/pandoc/fixtures';
$htmlDirectory = getenv('PANDOC_HTML_NATIVE_AST_DIR') ?: $defaultHtmlDirectory;
$limit = 0;
$requiredMappedParity = null;
$json = false;
$summary = false;

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help' || $argument === '-h') {
        fwrite(STDOUT, <<<'TXT'
Usage: php tools/pandoc-html-native-ast.php [--html-dir=PATH] [--limit=N] [--json] [--require-mapped-parity=N] [summary]

Compares local PHP HTML reader output with same-basename checked-in .native
expectations by normalized AST shape. Missing directories are reported as
skipped with exit 0 unless required parity is requested.

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

    if (str_starts_with($argument, '--html-dir=')) {
        $htmlDirectory = substr($argument, strlen('--html-dir='));
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

if ($htmlDirectory !== '' && !str_starts_with($htmlDirectory, DIRECTORY_SEPARATOR)) {
    $htmlDirectory = $repoRoot . DIRECTORY_SEPARATOR . $htmlDirectory;
}

$harness = new HtmlNativeAstComparisonHarness();
$report = $harness->run($htmlDirectory, ['limit' => $limit]);

if ($summary) {
    $report = array_intersect_key($report, array_flip([
        'schemaVersion',
        'tool',
        'status',
        'skipped',
        'reason',
        'verdict',
        'evidenceKind',
        'upstreamHtmlDirectory',
        'totalPairCount',
        'comparedPairCount',
        'htmlParsedCount',
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
    && !HtmlNativeAstComparisonHarness::hasRequiredMappedParity($report, $requiredMappedParity)
) {
    fwrite(
        STDERR,
        "pandoc-html-native-ast: normalized AST mapped parity did not match {$requiredMappedParity}/{$requiredMappedParity} paired fixtures\n"
    );
    exit(1);
}

exit(0);
