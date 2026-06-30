<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxNativeAstComparisonHarness;

require __DIR__ . '/bootstrap.php';

$repoRoot = dirname(__DIR__);
$defaultDocxDirectory = $repoRoot . '/.upstream-cache/pandoc-current/test/docx';
$docxDirectory = getenv('PANDOC_UPSTREAM_DOCX_DIR') ?: getenv('PANDOC_DOCX_NATIVE_AST_DIR') ?: $defaultDocxDirectory;
$limit = 0;
$json = false;

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help' || $argument === '-h') {
        fwrite(STDOUT, <<<'TXT'
Usage: php tools/pandoc-docx-native-ast.php [--upstream-docx-dir=PATH] [--limit=N] [--json]

Compares local PHP DOCX reader output with same-basename upstream .native
expectations by normalized AST shape when the upstream cache is present.
Missing cache is reported as skipped with exit 0.

TXT);
        exit(0);
    }

    if ($argument === '--json') {
        $json = true;
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

    fwrite(STDERR, "Unknown argument: {$argument}\n");
    exit(2);
}

if ($docxDirectory !== '' && !str_starts_with($docxDirectory, DIRECTORY_SEPARATOR)) {
    $docxDirectory = $repoRoot . DIRECTORY_SEPARATOR . $docxDirectory;
}

$harness = new DocxNativeAstComparisonHarness();
$report = $harness->run($docxDirectory, ['limit' => $limit]);

if ($json) {
    fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
} else {
    fwrite(STDOUT, $harness->formatReport($report));
}

exit(0);
