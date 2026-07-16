<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerServerAdapter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$uploadRoot = sys_get_temp_dir() . '/markerpdf-upload-pagination-smoke-' . bin2hex(random_bytes(4));
$adapter = new MarkerServerAdapter();
$params = [
    'max_pages' => '3',
    'langs' => 'English,Spanish',
    'force_ocr' => 'true',
    'paginate' => 'on',
    'extract_images' => '0',
];
$requests = [];

try {
    $plan = $adapter->uploadRoutePlan($params, $uploadRoot, local: false);
    $response = $adapter->convertPdfFromUpload(
        [
            'filename' => 'editorial-pagination.pdf',
            'content_type' => 'application/pdf',
            'bytes' => '%PDF-1.4 paginated remote upload boundary',
        ],
        $params,
        $uploadRoot,
        false,
        static fn (): string => 'unused-local-converter',
        static function (string $method, string $url, array $request) use (&$requests): array {
            $requests[] = [
                'method' => $method,
                'url' => $url,
                'headers' => $request['headers'] ?? [],
                'files' => $request['files'] ?? [],
            ];

            return ['status' => 'queued-without-request-check-url'];
        },
        'wp-import-api-key',
        'https://api.example/marker'
    );

    $uploadPath = $uploadRoot . DIRECTORY_SEPARATOR . 'editorial-pagination.pdf';
    $postFiles = $requests[0]['files'] ?? [];
    $payload = [
        'scenario' => 'wordpress-marker-runtime-server-upload-pagination-error-boundary-currentbase',
        'purpose' => 'Plan marker_server.py upload form pagination and remote error cleanup for a WordPress PDF import endpoint without FastAPI, Uvicorn, live HTTP, Python, or models.',
        'source' => 'sddai/markerPDF marker_server.py::convert_pdf_upload plus convert_pdf_from_upload',
        'upload_route' => [
            'endpoint' => $plan['endpoint'],
            'method' => $plan['method'],
            'selected_route' => $plan['selected_route'],
            'file_media_type' => $plan['file_field']['media_type'],
            'invalid_content_type_status_code' => $plan['file_field']['invalid_content_type_status_code'],
            'local_upload_applies_direct_marker_guard' => $plan['local_route']['applies_direct_marker_option_guard'],
            'remote_forwards_multipart_fields' => $plan['remote_route']['forwards_multipart_fields'],
        ],
        'request_count' => count($requests),
        'remote_filename' => $postFiles['file']['filename'] ?? null,
        'remote_paginate' => $postFiles['paginate'] ?? null,
        'remote_extract_images' => $postFiles['extract_images'] ?? null,
        'remote_max_pages' => $postFiles['max_pages'] ?? null,
        'remote_langs' => $postFiles['langs'] ?? null,
        'error_success' => $response['success'] ?? null,
        'error_contains_request_check_url' => str_contains((string) ($response['error'] ?? ''), 'request_check_url'),
        'upload_removed' => !is_file($uploadPath),
        'body_errors_return_success_false' => $plan['error_boundary']['body_errors_return_success_false'],
        'executes_fastapi' => false,
        'executes_uvicorn' => false,
        'executes_live_http' => false,
        'executes_python_or_models' => false,
        'executes_external_pdf_tools' => false,
    ];

    if ($payload['error_success'] !== false || !$payload['error_contains_request_check_url']) {
        throw new RuntimeException('Expected upload remote failure to return an API error payload.');
    }
    if ($payload['request_count'] !== 1 || $payload['remote_paginate'] !== true || $payload['remote_extract_images'] !== false) {
        throw new RuntimeException('Expected upload route to forward pagination/image fields to the remote request.');
    }
    if (!$payload['upload_removed']) {
        throw new RuntimeException('Expected upload route to remove the temporary PDF after remote failure.');
    }

    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    if (is_dir($uploadRoot)) {
        foreach (scandir($uploadRoot) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $uploadRoot . DIRECTORY_SEPARATOR . $entry;
            if (is_file($path)) {
                unlink($path);
            }
        }

        rmdir($uploadRoot);
    }
}
