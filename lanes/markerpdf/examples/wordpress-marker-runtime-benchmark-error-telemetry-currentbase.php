<?php

declare(strict_types=1);

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

$base = sys_get_temp_dir() . '/markerpdf-benchmark-error-telemetry-' . bin2hex(random_bytes(4));
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

    $response = (new BenchmarkRunner())->runWithErrorTelemetry(
        $pdfFolder,
        $referenceFolder,
        [
            'marker' => static function (string $pdfPath, string $document, string $reference, array $context) use ($pairsByDocument): string {
                if ($document === 'switch_trans.pdf') {
                    throw new RuntimeException('surya model boundary unavailable');
                }

                return $pairsByDocument[$document]['markerExcerpt'];
            },
        ],
        static fn (): int => 2,
        $markdownFolder,
        array_map(static fn (array $pair): int => $pair['chunkLength'], $pairsByDocument),
        null,
        ['profile_memory' => true]
    );

    if ($response['success'] !== false || !is_array($response['telemetry'])) {
        throw new RuntimeException('Expected benchmark runtime error telemetry.');
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-benchmark-error-telemetry-currentbase',
        'purpose' => 'Preserve benchmarks/overall.py fail-fast method/document error context for a WordPress PDF benchmark gate without executing Python, Torch, CUDA, models, Nougat, or external PDF tools.',
        'source' => 'sddai/markerPDF benchmarks/overall.py',
        'success' => $response['success'],
        'failed_phase' => $response['telemetry']['phase'],
        'failed_method' => $response['telemetry']['method'],
        'failed_document' => $response['telemetry']['document'],
        'failed_message_line' => $response['telemetry']['message_line'],
        'failed_traceback_available' => $response['telemetry']['traceback_available'],
        'failed_memory_snapshot' => $response['telemetry']['memory_snapshot'],
        'default_runner_fails_fast' => $response['telemetry']['default_runner_fails_fast'],
        'continues_after_failure' => $response['telemetry']['continues_after_failure'],
        'writes_failed_markdown' => is_file($markdownFolder . DIRECTORY_SEPARATOR . 'marker_switch_trans.md'),
        'preserved_prior_markdown' => is_file($markdownFolder . DIRECTORY_SEPARATOR . 'marker_multicolcnn.md'),
        'executes_external_tools' => $response['executes_external_tools'],
        'executes_python_or_models' => $response['executes_python_or_models'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($base);
}
