<?php

declare(strict_types=1);

use PortLibs\Pandoc\MarkdownNativeAstComparisonHarness;

require __DIR__ . '/bootstrap.php';

$repoRoot = dirname(__DIR__);
$defaultMarkdownDirectory = $repoRoot . '/lanes/pandoc/fixtures';
$markdownDirectory = getenv('PANDOC_MARKDOWN_NATIVE_AST_DIR') ?: $defaultMarkdownDirectory;
$limit = 0;
$requiredMappedParity = null;
$json = false;
$summary = false;

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help' || $argument === '-h') {
        fwrite(STDOUT, <<<'TXT'
Usage: php tools/pandoc-markdown-native-ast.php [--markdown-dir=PATH] [--limit=N] [--json] [--require-mapped-parity=N] [summary]

Compares local PHP Markdown reader output with same-basename checked-in .native
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

    if (str_starts_with($argument, '--markdown-dir=')) {
        $markdownDirectory = substr($argument, strlen('--markdown-dir='));
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

if ($markdownDirectory !== '' && !str_starts_with($markdownDirectory, DIRECTORY_SEPARATOR)) {
    $markdownDirectory = $repoRoot . DIRECTORY_SEPARATOR . $markdownDirectory;
}

$harness = new MarkdownNativeAstComparisonHarness();
$report = $harness->run($markdownDirectory, ['limit' => $limit]);

if ($summary) {
    $report = array_intersect_key($report, array_flip([
        'schemaVersion',
        'tool',
        'status',
        'skipped',
        'reason',
        'verdict',
        'evidenceKind',
        'upstreamMarkdownDirectory',
        'markdownReaderFixtureOptionOverrides',
        'markdownFixtureCount',
        'nativeFixtureCount',
        'pairedFixtureCount',
        'unpairedMarkdownFixtureCount',
        'unpairedNativeFixtureCount',
        'unpairedMarkdownFixtureNames',
        'unpairedNativeFixtureNames',
        'unpairedMarkdownFixtureExamples',
        'unpairedNativeFixtureExamples',
        'totalPairCount',
        'comparedPairCount',
        'markdownParsedCount',
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
    && !MarkdownNativeAstComparisonHarness::hasRequiredMappedParity($report, $requiredMappedParity)
) {
    fwrite(
        STDERR,
        "pandoc-markdown-native-ast: normalized AST mapped parity did not match {$requiredMappedParity}/{$requiredMappedParity} paired fixtures\n"
    );
    exit(1);
}

exit(0);
