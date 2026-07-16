<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BenchmarkRunner;
use PortLibs\MarkerPDF\MarkerServerAdapter;

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

$root = sys_get_temp_dir() . '/markerpdf-server-upload-benchmark-smoke-' . bin2hex(random_bytes(4));
$uploadRoot = $root . DIRECTORY_SEPARATOR . 'uploads';
$outputRoot = $root . DIRECTORY_SEPARATOR . 'benchmark-output';
$errorArtifactPath = $root . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . 'server-upload-error.json';

try {
    $requests = [];
    $serverResponse = (new MarkerServerAdapter())->convertPdfFromUpload(
        [
            'filename' => 'wp-upload-benchmark-error.pdf',
            'content_type' => 'application/pdf',
            'bytes' => '%PDF-1.4 WordPress upload benchmark error',
        ],
        [
            'max_pages' => 2,
            'langs' => 'English',
            'force_ocr' => false,
            'paginate' => true,
            'extract_images' => true,
        ],
        $uploadRoot,
        false,
        static fn (): string => 'unused-local-converter',
        static function (string $method, string $url, array $request) use (&$requests): array {
            $requests[] = [
                'method' => $method,
                'url' => $url,
                'filename' => $request['files']['file']['filename'] ?? null,
            ];

            return ['status' => 'queued-without-request-check-url'];
        },
        'wp-upload-benchmark-key-not-printed',
        'https://api.example/marker'
    );

    $uploadPath = $uploadRoot . DIRECTORY_SEPARATOR . 'wp-upload-benchmark-error.pdf';
    $result = (new BenchmarkRunner())->writeServerUploadBenchmarkResult(
        $outputRoot,
        $errorArtifactPath,
        'wp-upload-benchmark-error.pdf',
        $serverResponse,
        [
            'benchmark_index' => 0,
            'markdown_output_folder' => $outputRoot,
            'report_output' => $root . DIRECTORY_SEPARATOR . 'overall.json',
            'upload_removed' => !is_file($uploadPath),
            'request_count' => count($requests),
        ]
    );

    if (($result['success'] ?? null) !== false || ($result['result_kind'] ?? null) !== 'error_artifact') {
        throw new RuntimeException('Expected failed marker server upload to write a benchmark error artifact.');
    }
    if (($result['context']['upload_removed'] ?? null) !== true) {
        throw new RuntimeException('Expected failed marker server upload to remove the temporary PDF.');
    }
    if (is_file($outputRoot . DIRECTORY_SEPARATOR . 'wp-upload-benchmark-error' . DIRECTORY_SEPARATOR . 'wp-upload-benchmark-error.md')) {
        throw new RuntimeException('Expected failed benchmark upload to skip markdown output.');
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-server-upload-benchmark-error-currentbase',
        'purpose' => 'Convert a marker_server.py upload failure into a benchmarks/overall.py-style review artifact for a WordPress PDF benchmark queue without writing failed Markdown output.',
        'source' => $result['source'],
        'server_success' => $serverResponse['success'],
        'server_error' => $serverResponse['error'],
        'result_schema' => $result['schema'],
        'result_kind' => $result['result_kind'],
        'artifact_schema' => $result['error_artifact']['schema'],
        'artifact_sha256' => $result['error_artifact']['sha256'],
        'roundtrip_preserves_server_error' => $result['error_artifact']['roundtrip_preserves_server_error'],
        'upload_removed' => $result['context']['upload_removed'],
        'request_count' => $result['context']['request_count'],
        'benchmark_output_bundle_written' => $result['benchmark_output_bundle_written'],
        'success_report_written' => $result['success_report_written'],
        'writes_markdown_after_failure' => $result['writes_markdown_after_failure'],
        'executes_fastapi' => $result['executes_fastapi'],
        'executes_uvicorn' => $result['executes_uvicorn'],
        'executes_live_http' => $result['executes_live_http'],
        'executes_external_tools' => $result['executes_external_tools'],
        'executes_python_or_models' => $result['executes_python_or_models'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($root);
}
