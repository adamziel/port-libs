<?php

declare(strict_types=1);

use PortLibs\Pandoc\ManCorpusAudit;

require __DIR__ . '/bootstrap.php';

$repoRoot = dirname(__DIR__);
$roots = [];
$limit = 0;
$maxPandocNativeOutputBytes = null;
$pandocBin = getenv('PANDOC_BIN') ?: null;
$comparePandoc = true;
$targetDialects = [];
$json = false;
$summary = false;
$requireTargetFilesMin = null;
$requireLocalParseMin = null;
$requireNoLocalParseFailures = false;
$requireNoControlLeaks = false;
$requirePandocParseMin = null;
$requireNormalizedMatchMin = null;

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help' || $argument === '-h') {
        fwrite(STDOUT, <<<'TXT'
Usage: php tools/pandoc-man-corpus-audit.php [options] [summary]

Options:
  --man-root=PATH                    Manpage file or directory to audit. Repeatable. Defaults to /usr/share/man when present.
  --limit=N                          Audit at most N candidate files after sorting. 0 means no limit.
  --max-pandoc-native-output-bytes=N  Skip NativeReader parsing for a pandoc native transcript above N bytes.
                                      0 disables the guard. Defaults to the audit's safe bound.
  --pandoc-bin=PATH                  Pandoc executable to use for optional native comparison.
  --no-pandoc                        Skip pandoc executable comparison.
  --target-dialect=FORMAT            Target dialect to audit. Repeatable. Defaults to man. Supports man and mdoc.
  --json                             Emit JSON instead of text.
  --require-target-files-min=N       Exit 1 unless at least N target man-dialect files are audited.
  --require-local-parse-min=N        Exit 1 unless at least N target files parse locally.
  --require-no-local-parse-failures  Exit 1 unless local parse failures are zero.
  --require-no-control-leaks         Exit 1 unless visible roff control-request leak count is zero.
  --require-pandoc-parse-min=N       Exit 1 unless pandoc parses at least N target files.
  --require-normalized-match-min=N   Exit 1 unless local and pandoc normalized ASTs match at least N target files.

This is a real-world/fixture corpus confidence audit for manpage dialects. It
targets the man dialect by default and does not claim full roff, man, or mdoc
parity.

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

    if ($argument === '--no-pandoc') {
        $comparePandoc = false;
        continue;
    }

    if (str_starts_with($argument, '--target-dialect=')) {
        $targetDialect = substr($argument, strlen('--target-dialect='));
        if (!in_array($targetDialect, ['man', 'mdoc'], true)) {
            fwrite(STDERR, "--target-dialect supports only man or mdoc\n");
            exit(2);
        }
        $targetDialects[] = $targetDialect;
        continue;
    }

    if (str_starts_with($argument, '--man-root=')) {
        $roots[] = substr($argument, strlen('--man-root='));
        continue;
    }

    if (str_starts_with($argument, '--limit=')) {
        $limit = max(0, (int) substr($argument, strlen('--limit=')));
        continue;
    }

    if (str_starts_with($argument, '--max-pandoc-native-output-bytes=')) {
        $maxPandocNativeOutputBytes = parseNonNegativeInt($argument, '--max-pandoc-native-output-bytes=');
        continue;
    }

    if (str_starts_with($argument, '--pandoc-bin=')) {
        $pandocBin = substr($argument, strlen('--pandoc-bin='));
        continue;
    }

    if (str_starts_with($argument, '--require-target-files-min=')) {
        $requireTargetFilesMin = parseNonNegativeInt($argument, '--require-target-files-min=');
        continue;
    }

    if (str_starts_with($argument, '--require-local-parse-min=')) {
        $requireLocalParseMin = parseNonNegativeInt($argument, '--require-local-parse-min=');
        continue;
    }

    if ($argument === '--require-no-local-parse-failures') {
        $requireNoLocalParseFailures = true;
        continue;
    }

    if ($argument === '--require-no-control-leaks') {
        $requireNoControlLeaks = true;
        continue;
    }

    if (str_starts_with($argument, '--require-pandoc-parse-min=')) {
        $requirePandocParseMin = parseNonNegativeInt($argument, '--require-pandoc-parse-min=');
        continue;
    }

    if (str_starts_with($argument, '--require-normalized-match-min=')) {
        $requireNormalizedMatchMin = parseNonNegativeInt($argument, '--require-normalized-match-min=');
        continue;
    }

    fwrite(STDERR, "Unknown argument: {$argument}\n");
    exit(2);
}

if ($roots === []) {
    $roots = is_dir('/usr/share/man') ? ['/usr/share/man'] : [];
}

$roots = array_map(static function (string $root) use ($repoRoot): string {
    if ($root !== '' && !str_starts_with($root, DIRECTORY_SEPARATOR)) {
        return $repoRoot . DIRECTORY_SEPARATOR . $root;
    }

    return $root;
}, $roots);

$audit = new ManCorpusAudit();
$report = $audit->run($roots, [
    'limit' => $limit,
    'pandocBin' => $pandocBin,
    'comparePandoc' => $comparePandoc,
    'targetDialects' => $targetDialects,
    ...($maxPandocNativeOutputBytes === null ? [] : ['maxPandocNativeOutputBytes' => $maxPandocNativeOutputBytes]),
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
        'roots',
        'rootInventory',
        'targetDialects',
        'limit',
        'maxPandocNativeOutputBytes',
        'totalCandidateFileCount',
        'auditedFileCount',
        'dialectCounts',
        'targetFileCount',
        'nonTargetFileCount',
        'localParsedCount',
        'localParseFailureCount',
        'localControlLeakCount',
        'pandocComparisonStatus',
        'pandocExecutable',
        'pandocVersion',
        'pandocParsedCount',
        'pandocParseFailureCount',
        'bothParsedCount',
        'normalizedAstMatchCount',
        'normalizedAstMismatchCount',
        'normalizedAstMatchPercent',
        'visibleTextMatchCount',
        'visibleTextMismatchCount',
        'visibleTextMatchPercent',
        'corpusStatus',
        'mismatchCategories',
        'orderedRemainingGaps',
    ]));
}

if ($json) {
    fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
} else {
    fwrite(STDOUT, $audit->formatReport($report));
}

if ($requireTargetFilesMin !== null && !ManCorpusAudit::hasRequiredTargetFiles($report, $requireTargetFilesMin)) {
    fwrite(STDERR, "pandoc-man-corpus-audit: target manual file count is below {$requireTargetFilesMin}\n");
    exit(1);
}

if ($requireLocalParseMin !== null && !ManCorpusAudit::hasRequiredLocalParse($report, $requireLocalParseMin)) {
    fwrite(STDERR, "pandoc-man-corpus-audit: local parsed manual file count is below {$requireLocalParseMin}\n");
    exit(1);
}

if ($requireNoLocalParseFailures && !ManCorpusAudit::hasNoLocalParseFailures($report)) {
    fwrite(STDERR, "pandoc-man-corpus-audit: local parse failures were reported\n");
    exit(1);
}

if ($requireNoControlLeaks && !ManCorpusAudit::hasNoControlLeaks($report)) {
    fwrite(STDERR, "pandoc-man-corpus-audit: visible roff control-request leaks were reported\n");
    exit(1);
}

if ($requirePandocParseMin !== null && !ManCorpusAudit::hasRequiredPandocParse($report, $requirePandocParseMin)) {
    fwrite(STDERR, "pandoc-man-corpus-audit: pandoc parsed manual file count is below {$requirePandocParseMin}\n");
    exit(1);
}

if ($requireNormalizedMatchMin !== null && !ManCorpusAudit::hasRequiredNormalizedMatches($report, $requireNormalizedMatchMin)) {
    fwrite(STDERR, "pandoc-man-corpus-audit: normalized AST match count is below {$requireNormalizedMatchMin}\n");
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
