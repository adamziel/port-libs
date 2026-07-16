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

$root = sys_get_temp_dir() . '/markerpdf-server-benchmark-roundtrip-smoke-' . bin2hex(random_bytes(4));
$uploadRoot = $root . DIRECTORY_SEPARATOR . 'uploads';
$artifactPath = $root . DIRECTORY_SEPARATOR . 'benchmark' . DIRECTORY_SEPARATOR . 'server-error.json';
mkdir(dirname($artifactPath), 0777, true);

try {
    $requests = [];
    $serverResponse = (new MarkerServerAdapter())->convertPdfFromUpload(
        [
            'filename' => 'wp-review-server-error.pdf',
            'content_type' => 'application/pdf',
            'bytes' => '%PDF-1.4 server benchmark output error roundtrip',
        ],
        [
            'max_pages' => 1,
            'langs' => 'English',
            'force_ocr' => false,
            'paginate' => false,
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
        'wp-demo-key-not-printed',
        'https://api.example/marker'
    );

    $uploadPath = $uploadRoot . DIRECTORY_SEPARATOR . 'wp-review-server-error.pdf';
    if (($serverResponse['success'] ?? null) !== false) {
        throw new RuntimeException('Expected marker server upload to return a failed response.');
    }
    if (is_file($uploadPath)) {
        throw new RuntimeException('Expected marker server upload cleanup after failed response.');
    }

    $runner = new BenchmarkRunner();
    $artifact = $runner->writeServerBenchmarkErrorArtifactJson(
        $artifactPath,
        $serverResponse,
        [
            'phase' => 'server_upload',
            'method' => 'marker',
            'document' => 'wp-review-server-error.pdf',
            'benchmark_index' => 0,
            'markdown_output_folder' => $root . DIRECTORY_SEPARATOR . 'markdown',
            'report_output' => $root . DIRECTORY_SEPARATOR . 'overall.json',
            'upload_removed' => !is_file($uploadPath),
            'request_count' => count($requests),
        ]
    );
    $roundtrip = $runner->readServerBenchmarkErrorArtifactJson($artifactPath);

    if ($roundtrip['roundtrip_preserves_server_error'] !== true) {
        throw new RuntimeException('Expected artifact readback to preserve the marker server error.');
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-server-benchmark-output-error-roundtrip-currentbase',
        'purpose' => 'Roundtrip marker_server.py upload error payloads through a benchmarks/overall.py-style review JSON artifact for WordPress benchmark gates without launching Uvicorn, FastAPI, Python, models, Nougat, or external PDF tools.',
        'source' => $roundtrip['payload']['source'],
        'server_success' => $serverResponse['success'],
        'server_error' => $serverResponse['error'],
        'artifact_basename' => basename($artifactPath),
        'artifact_schema' => $artifact['schema'],
        'artifact_sha256_matches_readback' => $artifact['sha256'] === $roundtrip['sha256'],
        'roundtrip_preserves_server_error' => $roundtrip['roundtrip_preserves_server_error'],
        'upload_removed' => $roundtrip['payload']['context']['upload_removed'],
        'request_count' => $roundtrip['payload']['context']['request_count'],
        'success_report_written' => $roundtrip['payload']['success_report_written'],
        'writes_markdown_after_failure' => $roundtrip['payload']['writes_markdown_after_failure'],
        'executes_fastapi' => $roundtrip['payload']['executes_fastapi'],
        'executes_uvicorn' => $roundtrip['payload']['executes_uvicorn'],
        'executes_live_http' => $roundtrip['payload']['executes_live_http'],
        'executes_external_tools' => $roundtrip['payload']['executes_external_tools'],
        'executes_python_or_models' => $roundtrip['payload']['executes_python_or_models'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($root);
}
