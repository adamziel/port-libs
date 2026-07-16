<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BenchmarkReportVerifier;
use PortLibs\MarkerPDF\BenchmarkRunner;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $child = $path . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($child)) {
            $removeTree($child);
        } else {
            unlink($child);
        }
    }

    rmdir($path);
};

$base = sys_get_temp_dir() . '/markerpdf-benchmark-callback-sandbox-' . bin2hex(random_bytes(4));
$pdfFolder = $base . '/pdfs';
$referenceFolder = $base . '/references';
$markdownFolder = $base . '/markdown';

mkdir($pdfFolder, 0777, true);
mkdir($referenceFolder, 0777, true);
mkdir($markdownFolder, 0777, true);

try {
    $fixture = require dirname(__DIR__) . '/fixtures/upstream-ci-benchmark-short.php';
    $pairsByDocument = [];
    foreach ($fixture['benchmarkPairs'] as $pair) {
        $pairsByDocument[$pair['document']] = $pair;
        file_put_contents(
            $pdfFolder . DIRECTORY_SEPARATOR . $pair['document'],
            "%PDF-1.4\n% callback sandbox " . $pair['document'] . "\n%%EOF"
        );
        file_put_contents(
            $referenceFolder . DIRECTORY_SEPARATOR . preg_replace('/\.[^.]*$/', '.md', $pair['document']),
            $pair['referenceExcerpt']
        );
    }

    $runner = new BenchmarkRunner();
    $contexts = [];
    $result = $runner->run(
        $pdfFolder,
        $referenceFolder,
        [
            'marker' => static function (
                string $pdfPath,
                string $document,
                string $reference,
                array $context
            ) use (&$contexts, $pairsByDocument): string {
                $contexts[] = [
                    'document' => $document,
                    'callback_sandbox' => $context['callback_sandbox'],
                    'executes_external_tools' => $context['executes_external_tools'],
                ];

                return $pairsByDocument[$document]['markerExcerpt'];
            },
        ],
        static fn (string $pdfPath): int => str_contains($pdfPath, 'switch_trans') ? 4 : 3,
        $markdownFolder,
        array_map(static fn (array $pair): int => $pair['chunkLength'], $pairsByDocument)
    );
    (new BenchmarkReportVerifier())->verifyMarkerScores($result['report']);

    $blockedSandboxMutation = false;
    $blockedMessage = null;
    try {
        $runner->run(
            $pdfFolder,
            $referenceFolder,
            [
                'marker' => static function () use ($markdownFolder): string {
                    file_put_contents($markdownFolder . DIRECTORY_SEPARATOR . 'rogue-callback.md', 'callback artifact');

                    return 'unsafe callback output';
                },
            ],
            static fn (): int => 1,
            $markdownFolder
        );
    } catch (RuntimeException $exception) {
        $blockedSandboxMutation = true;
        $blockedMessage = $exception->getMessage();
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-benchmark-callback-sandbox-currentbase',
        'purpose' => 'Keep supplied benchmark callbacks from mutating staged PDF/reference/output artifacts before WordPress import review trusts upstream-style benchmark scores.',
        'benchmark_files' => $result['benchmark_files'],
        'written_markdown' => array_map('basename', $result['written_markdown']),
        'callback_sandbox' => $result['runtime']['callback_sandbox'],
        'callback_contexts' => $contexts,
        'blocked_rogue_callback_write' => $blockedSandboxMutation,
        'blocked_message' => $blockedMessage,
        'passes_upstream_ci_marker_thresholds' => true,
        'executes_python_or_models' => false,
        'executes_external_pdf_tools' => false,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($base);
}
