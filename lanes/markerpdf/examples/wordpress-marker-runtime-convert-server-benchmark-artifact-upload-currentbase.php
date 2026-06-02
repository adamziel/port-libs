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

$root = sys_get_temp_dir() . '/markerpdf-server-benchmark-upload-smoke-' . bin2hex(random_bytes(4));
$uploadRoot = $root . DIRECTORY_SEPARATOR . 'uploads';
$artifactPath = $root . DIRECTORY_SEPARATOR . 'benchmark' . DIRECTORY_SEPARATOR . 'server-upload.json';
mkdir(dirname($artifactPath), 0777, true);

try {
    $requests = [];
    $serverResponse = (new MarkerServerAdapter())->convertPdfFromUpload(
        [
            'filename' => '../wp benchmark upload.pdf',
            'content_type' => 'application/pdf',
            'bytes' => "%PDF-1.4\n% wordpress benchmark source bytes\n%%EOF",
        ],
        [
            'max_pages' => 2,
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

            return $method === 'POST'
                ? ['request_check_url' => 'https://api.example/check/wp-server-upload']
                : [
                    'status' => 'complete',
                    'success' => true,
                    'markdown' => "# WordPress benchmark upload\n\nConverted server response body.",
                    'images' => ['page-1.png' => base64_encode('PNG wordpress artifact bytes')],
                    'metadata' => ['pages' => 2, 'source' => 'marker_server_upload'],
                ];
        },
        'wp-demo-key-not-printed',
        'https://api.example/marker'
    );

    $uploadPath = $uploadRoot . DIRECTORY_SEPARATOR . 'wp benchmark upload.pdf';
    if (($serverResponse['success'] ?? null) !== true || is_file($uploadPath)) {
        throw new RuntimeException('Expected successful marker server upload response and temporary upload cleanup.');
    }

    $runner = new BenchmarkRunner();
    $artifact = $runner->writeServerBenchmarkUploadArtifactJson(
        $artifactPath,
        $serverResponse,
        [
            'phase' => 'server_upload_success',
            'method' => 'marker',
            'document' => 'wp benchmark upload.pdf',
            'benchmark_index' => 0,
            'markdown_output_folder' => $root . DIRECTORY_SEPARATOR . 'markdown',
            'markdown_output' => $root . DIRECTORY_SEPARATOR . 'markdown' . DIRECTORY_SEPARATOR . 'marker_wp_benchmark_upload.md',
            'report_output' => $root . DIRECTORY_SEPARATOR . 'overall.json',
            'uploaded_filename' => 'wp benchmark upload.pdf',
            'server_route' => 'remote',
            'upload_removed' => !is_file($uploadPath),
            'request_count' => count($requests),
            'pages' => 2,
            'score' => 0.91,
            'time' => 0.33,
            'success_report_written' => true,
        ]
    );
    $roundtrip = $runner->readServerBenchmarkUploadArtifactJson($artifactPath);
    $artifactJson = (string) file_get_contents($artifactPath);

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-convert-server-benchmark-artifact-upload-currentbase',
        'purpose' => 'Archive a successful marker_server.py upload conversion as a benchmarks/overall.py-style WordPress review artifact without storing uploaded PDF bytes, image payloads, or executing FastAPI, Uvicorn, Python, models, Nougat, or external PDF tools.',
        'source' => $roundtrip['payload']['source'],
        'server_success' => $serverResponse['success'],
        'request_methods' => array_column($requests, 'method'),
        'uploaded_filename' => $roundtrip['payload']['context']['uploaded_filename'],
        'upload_removed' => $roundtrip['payload']['context']['upload_removed'],
        'artifact_basename' => basename($artifactPath),
        'artifact_schema' => $artifact['schema'],
        'artifact_sha256_matches_readback' => $artifact['sha256'] === $roundtrip['sha256'],
        'roundtrip_preserves_server_success' => $roundtrip['roundtrip_preserves_server_success'],
        'roundtrip_preserves_markdown_hash' => $roundtrip['roundtrip_preserves_markdown_hash'],
        'markdown_sha256' => $roundtrip['payload']['server_response']['markdown_sha256'],
        'image_payloads_summarized' => $roundtrip['payload']['server_response']['images_are_summarized'],
        'image_count' => $roundtrip['payload']['server_response']['image_count'],
        'raw_upload_bytes_excluded' => !str_contains($artifactJson, 'wordpress benchmark source bytes'),
        'raw_image_base64_excluded' => !str_contains($artifactJson, base64_encode('PNG wordpress artifact bytes')),
        'success_report_written' => $roundtrip['payload']['artifact']['success_report_written'],
        'executes_fastapi' => $roundtrip['payload']['executes_fastapi'],
        'executes_uvicorn' => $roundtrip['payload']['executes_uvicorn'],
        'executes_live_http' => $roundtrip['payload']['executes_live_http'],
        'executes_external_tools' => $roundtrip['payload']['executes_external_tools'],
        'executes_python_or_models' => $roundtrip['payload']['executes_python_or_models'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($root);
}
