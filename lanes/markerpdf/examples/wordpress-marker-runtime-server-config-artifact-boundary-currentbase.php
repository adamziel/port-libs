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

$root = sys_get_temp_dir() . '/markerpdf-server-config-artifact-smoke-' . bin2hex(random_bytes(4));
$uploadRoot = $root . DIRECTORY_SEPARATOR . 'uploads';
$artifactPath = $root . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'server-config.json';

try {
    $adapter = new MarkerServerAdapter();
    $configBoundary = $adapter->serverConfigErrorBoundary([
        'host' => '0.0.0.0',
        'port' => '9123',
        'api_key' => 'wp-demo-server-config-secret',
        'datalab_url' => 'https://api.example/marker',
        'upload_directory' => $uploadRoot,
        'ensure_upload_directory' => true,
    ]);
    $uploadPlan = $adapter->uploadRoutePlan(
        [
            'max_pages' => 2,
            'langs' => 'English,French',
            'force_ocr' => true,
            'paginate' => true,
            'extract_images' => false,
        ],
        $uploadRoot,
        false
    );

    $serverResponse = $adapter->convertPdfFromUpload(
        [
            'filename' => '../wp-config-artifact.pdf',
            'content_type' => 'application/pdf',
            'bytes' => '%PDF-1.4 WordPress config artifact source bytes',
        ],
        [
            'max_pages' => 2,
            'langs' => 'English,French',
            'force_ocr' => true,
            'paginate' => true,
            'extract_images' => false,
        ],
        $uploadRoot,
        false,
        static fn (): string => 'unused-local-converter',
        static function (string $method): array {
            return $method === 'POST'
                ? ['request_check_url' => 'https://api.example/check/wp-config-artifact']
                : [
                    'status' => 'complete',
                    'success' => true,
                    'markdown' => 'WordPress config artifact import.',
                    'images' => [],
                    'metadata' => ['route' => 'remote'],
                ];
        },
        'wp-demo-server-config-secret',
        'https://api.example/marker'
    );

    if (($configBoundary['success'] ?? null) !== true || ($serverResponse['success'] ?? null) !== true) {
        throw new RuntimeException('Expected marker server config and conversion boundaries to succeed.');
    }

    $uploadPath = $uploadRoot . DIRECTORY_SEPARATOR . 'wp-config-artifact.pdf';
    $artifact = (new BenchmarkRunner())->writeServerConfigArtifactJson(
        $artifactPath,
        $configBoundary,
        $uploadPlan,
        [
            'phase' => 'server_config',
            'method' => 'marker',
            'document' => 'wp-config-artifact.pdf',
            'benchmark_index' => 0,
            'server_route' => 'remote',
            'uploaded_filename' => 'wp-config-artifact.pdf',
            'upload_removed' => !is_file($uploadPath),
            'request_count' => 2,
            'server_success' => $serverResponse['success'],
            'server_response_status' => $serverResponse['status'],
            'markdown_output_folder' => $root . DIRECTORY_SEPARATOR . 'markdown',
            'report_output' => $root . DIRECTORY_SEPARATOR . 'overall.json',
        ]
    );
    $roundtrip = (new BenchmarkRunner())->readServerConfigArtifactJson($artifactPath);
    $artifactJson = (string) file_get_contents($artifactPath);

    $payload = [
        'scenario' => 'wordpress-marker-runtime-server-config-artifact-boundary-currentbase',
        'purpose' => 'Persist marker_server.py startup config plus upload-route boundaries as a WordPress benchmark review artifact without exposing API keys, uploaded PDF bytes, FastAPI, Uvicorn, Python, model workers, live HTTP, or external PDF tools.',
        'source' => $roundtrip['payload']['source'],
        'artifact_schema' => $artifact['schema'],
        'artifact_status' => $artifact['status'],
        'artifact_sha256_matches_readback' => $artifact['sha256'] === $roundtrip['sha256'],
        'roundtrip_preserves_config_boundary' => $roundtrip['roundtrip_preserves_config_boundary'],
        'api_key_configured' => $artifact['api_key_configured'],
        'raw_api_key_excluded' => !str_contains($artifactJson, 'wp-demo-server-config-secret'),
        'uploaded_pdf_bytes_excluded' => !str_contains($artifactJson, 'WordPress config artifact source bytes'),
        'upload_directory_created' => $artifact['upload_directory_created'],
        'upload_removed' => $artifact['upload_removed'],
        'server_success' => $serverResponse['success'],
        'upload_route' => $roundtrip['payload']['upload_route']['selected_route'],
        'forwards_multipart_fields' => $roundtrip['payload']['upload_route']['forwards_multipart_fields'],
        'default_upload_temp_file_removed_after_conversion' => $roundtrip['payload']['default_upload_temp_file_removed_after_conversion'],
        'executes_fastapi' => $roundtrip['executes_fastapi'],
        'executes_uvicorn' => $roundtrip['executes_uvicorn'],
        'executes_live_http' => $roundtrip['executes_live_http'],
        'executes_external_tools' => $roundtrip['executes_external_tools'],
        'executes_python_or_models' => $roundtrip['executes_python_or_models'],
    ];

    if ($payload['raw_api_key_excluded'] !== true || $payload['uploaded_pdf_bytes_excluded'] !== true) {
        throw new RuntimeException('Expected config artifact to exclude secrets and uploaded PDF bytes.');
    }
    if ($payload['upload_directory_created'] !== true || $payload['upload_removed'] !== true) {
        throw new RuntimeException('Expected config artifact to preserve upload directory and cleanup boundaries.');
    }

    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($root);
}
