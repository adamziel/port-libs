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

$base = sys_get_temp_dir() . '/markerpdf-wordpress-benchmark-' . bin2hex(random_bytes(4));
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
        file_put_contents($pdfFolder . '/' . $pair['document'], "%PDF-1.4\n% WordPress supplied conversion " . $pair['document'] . "\n%%EOF");
        file_put_contents($referenceFolder . '/' . preg_replace('/\.[^.]*$/', '.md', $pair['document']), $pair['referenceExcerpt']);
    }

    $result = (new BenchmarkRunner())->run(
        $pdfFolder,
        $referenceFolder,
        [
            'marker' => static fn (string $pdfPath, string $document): string => $pairsByDocument[$document]['markerExcerpt'],
            'nougat' => static fn (string $pdfPath, string $document, string $reference): string => $reference,
        ],
        static fn (string $pdfPath): int => str_contains($pdfPath, 'switch_trans') ? 4 : 3,
        $markdownFolder,
        array_map(static fn (array $pair): int => $pair['chunkLength'], $pairsByDocument),
        null,
        [
            'nougat' => true,
            'marker_batch_multiplier' => 2,
            'nougat_batch_size' => 1,
            'profile_memory' => true,
        ]
    );
    (new BenchmarkReportVerifier())->verifyMarkerScores($result['report']);

    echo json_encode([
        'scenario' => 'wordpress-pdf-benchmark-runner',
        'purpose' => 'Run a supplied native conversion over upstream CI benchmark references before a WordPress PDF import batch reaches editorial review.',
        'archive' => [
            'filename' => $fixture['archive']['filename'],
            'sha256' => $fixture['archive']['sha256'],
        ],
        'benchmark_files' => $result['benchmark_files'],
        'written_markdown' => array_map('basename', $result['written_markdown']),
        'runtime' => $result['runtime'],
        'report' => $result['report'],
        'passes_upstream_ci_marker_thresholds' => true,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($base);
}
