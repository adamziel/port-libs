<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerServerAdapter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$uploadRoot = sys_get_temp_dir() . '/markerpdf-server-error-boundary-' . bin2hex(random_bytes(4));
$adapter = new MarkerServerAdapter();
$requests = [];

try {
    $response = $adapter->convertPdfFromUpload(
        [
            'filename' => 'editorial-remote-error.pdf',
            'content_type' => 'application/pdf',
            'bytes' => '%PDF-1.4 remote conversion error boundary',
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
        'wp-import-api-key',
        'https://api.example/marker'
    );

    $uploadPath = $uploadRoot . DIRECTORY_SEPARATOR . 'editorial-remote-error.pdf';
    if (($response['success'] ?? null) !== false) {
        throw new RuntimeException('Expected failed marker server conversion response.');
    }
    if (!str_contains((string) ($response['error'] ?? ''), 'request_check_url')) {
        throw new RuntimeException('Expected request_check_url error boundary metadata.');
    }
    if (is_file($uploadPath)) {
        throw new RuntimeException('Expected failed upload conversion to remove the temporary PDF.');
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-server-convert-error-boundary-currentbase',
        'purpose' => 'Keep marker_server.py upload conversion failures as API error payloads for a WordPress import endpoint while removing the temporary PDF.',
        'success' => $response['success'],
        'error' => $response['error'],
        'request_count' => count($requests),
        'remote_filename' => $requests[0]['filename'] ?? null,
        'upload_removed' => !is_file($uploadPath),
        'executes_fastapi' => false,
        'executes_uvicorn' => false,
        'executes_python_or_models' => false,
        'executes_external_pdf_tools' => false,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
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
