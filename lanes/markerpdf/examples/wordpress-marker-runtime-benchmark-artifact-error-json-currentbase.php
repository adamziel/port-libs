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

$base = sys_get_temp_dir() . '/markerpdf-benchmark-artifact-error-' . bin2hex(random_bytes(4));
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

    $successReport = $markdownFolder . DIRECTORY_SEPARATOR . 'missing' . DIRECTORY_SEPARATOR . 'overall.json';
    $errorArtifact = $markdownFolder . DIRECTORY_SEPARATOR . 'overall.error.json';
    $response = (new BenchmarkRunner())->runWithErrorTelemetry(
        $pdfFolder,
        $referenceFolder,
        [
            'marker' => static fn (string $pdfPath, string $document): string => $pairsByDocument[$document]['markerExcerpt'],
        ],
        static fn (): int => 2,
        $markdownFolder,
        array_map(static fn (array $pair): int => $pair['chunkLength'], $pairsByDocument),
        $successReport,
        [],
        $errorArtifact
    );

    if ($response['success'] !== false || !is_file($errorArtifact)) {
        throw new RuntimeException('Expected benchmark error JSON artifact.');
    }

    $payload = json_decode((string) file_get_contents($errorArtifact), true, flags: JSON_THROW_ON_ERROR);

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-benchmark-artifact-error-json-currentbase',
        'purpose' => 'Persist benchmarks/overall.py final report failures as review-only JSON for a WordPress PDF benchmark gate without executing Python, CUDA, models, Nougat, or external PDF tools.',
        'source' => 'sddai/markerPDF benchmarks/overall.py',
        'success' => $response['success'],
        'failed_phase' => $response['telemetry']['phase'],
        'failed_message_line' => $response['telemetry']['message_line'],
        'success_report_written' => is_file($successReport),
        'error_artifact_basename' => basename($errorArtifact),
        'error_artifact_schema' => $payload['schema'],
        'error_artifact_review_only' => $payload['review_only'],
        'error_artifact_success_report_written' => $payload['success_report_written'],
        'written_markdown' => [
            'marker_multicolcnn.md' => is_file($markdownFolder . DIRECTORY_SEPARATOR . 'marker_multicolcnn.md'),
            'marker_switch_trans.md' => is_file($markdownFolder . DIRECTORY_SEPARATOR . 'marker_switch_trans.md'),
        ],
        'executes_external_tools' => $response['executes_external_tools'],
        'executes_python_or_models' => $response['executes_python_or_models'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($base);
}
