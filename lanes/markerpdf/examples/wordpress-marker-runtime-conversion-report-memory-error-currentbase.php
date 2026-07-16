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

$base = sys_get_temp_dir() . '/markerpdf-runtime-memory-report-' . bin2hex(random_bytes(4));
$pdfFolder = $base . DIRECTORY_SEPARATOR . 'pdfs';
$referenceFolder = $base . DIRECTORY_SEPARATOR . 'references';
$markdownFolder = $base . DIRECTORY_SEPARATOR . 'markdown';
mkdir($pdfFolder, 0777, true);
mkdir($referenceFolder, 0777, true);
mkdir($markdownFolder, 0777, true);

try {
    $fixture = require dirname(__DIR__) . '/fixtures/upstream-ci-benchmark-short.php';
    $pairsByDocument = [];
    foreach ($fixture['benchmarkPairs'] as $pair) {
        $pairsByDocument[$pair['document']] = $pair;
        file_put_contents($pdfFolder . DIRECTORY_SEPARATOR . $pair['document'], "%PDF-1.4\n% " . $pair['document'] . "\n%%EOF");
        file_put_contents(
            $referenceFolder . DIRECTORY_SEPARATOR . preg_replace('/\.[^.]*$/', '.md', $pair['document']),
            $pair['referenceExcerpt']
        );
    }

    $result = (new BenchmarkRunner())->run(
        $pdfFolder,
        $referenceFolder,
        [
            'marker' => static fn (string $pdfPath, string $document): string => $pairsByDocument[$document]['markerExcerpt'],
        ],
        static fn (): int => 2,
        $markdownFolder,
        array_map(static fn (array $pair): int => $pair['chunkLength'], $pairsByDocument),
        null,
        [
            'profile_memory' => true,
            'memory_snapshot_errors' => [
                'model_load.pickle' => 'CUDA model snapshot unavailable',
                'marker_memory_1.pickle' => 'CUDA conversion snapshot unavailable',
            ],
        ]
    );
    (new BenchmarkReportVerifier())->verifyMarkerScores($result['report']);

    $failures = $result['runtime']['memory_snapshot_failures'];
    if ($result['runtime']['memory_snapshot_failure_count'] !== 2 || $failures[1]['continues_after_failure'] !== true) {
        throw new RuntimeException('Expected fail-soft memory snapshot report rows.');
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-conversion-report-memory-error-currentbase',
        'purpose' => 'Preserve upstream benchmarks/overall.py memory profiling dump errors inside the successful conversion report for WordPress benchmark review without executing CUDA, Python, models, or external PDF tools.',
        'source' => 'sddai/markerPDF benchmarks/overall.py',
        'benchmark_files' => $result['benchmark_files'],
        'written_markdown' => array_map('basename', $result['written_markdown']),
        'model_load_snapshot' => $result['runtime']['model_load_snapshot'],
        'conversion_snapshots' => $result['runtime']['conversion_snapshots'],
        'memory_snapshot_failure_count' => $result['runtime']['memory_snapshot_failure_count'],
        'memory_snapshot_failures' => array_map(
            static fn (array $failure): array => [
                'phase' => $failure['phase'],
                'method' => $failure['method'],
                'document' => $failure['document'],
                'snapshot' => $failure['snapshot'],
                'log_line' => $failure['log_line'],
                'continues_after_failure' => $failure['continues_after_failure'],
                'recording_disabled_after_error' => $failure['recording_disabled_after_error'],
                'executes_cuda_memory_history' => $failure['executes_cuda_memory_history'],
                'review_only' => $failure['review_only'],
            ],
            $failures
        ),
        'report_avg_score' => $result['report']['marker']['avg_score'],
        'continues_after_memory_snapshot_failure' => $result['runtime']['continues_after_memory_snapshot_failure'],
        'executes_external_tools' => $result['runtime']['executes_external_tools'],
        'executes_python_or_models' => false,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($base);
}
