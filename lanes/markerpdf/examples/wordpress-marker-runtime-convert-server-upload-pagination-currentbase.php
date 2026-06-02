<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerServerAdapter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/markerpdf-server-upload-pagination-smoke-' . bin2hex(random_bytes(4));
$uploadRoot = $root . DIRECTORY_SEPARATOR . 'uploads';

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

try {
    $separator = MarkerServerAdapter::UPSTREAM_PAGE_SEPARATOR;
    $markdown = "\n\n{7}" . $separator . "\n\nWordPress import page seven."
        . "\n\n{8}" . $separator . "\n\nWordPress import page eight.";
    $upload = [
        'filename' => '../wp server upload pagination.pdf',
        'content_type' => 'application/pdf',
        'bytes' => "%PDF-1.7\nwordpress server upload pagination source bytes\n%%EOF",
    ];
    $params = [
        'max_pages' => '2',
        'langs' => 'English',
        'force_ocr' => 'true',
        'paginate' => 'on',
        'extract_images' => '0',
    ];
    $requests = [];
    $adapter = new MarkerServerAdapter();
    $response = $adapter->convertPdfFromUpload(
        $upload,
        $params,
        $uploadRoot,
        false,
        static fn (): string => 'unused-local-converter',
        static function (string $method, string $url, array $request) use (&$requests, $markdown): array {
            $requests[] = ['method' => $method, 'url' => $url, 'request' => $request];

            return $method === 'POST'
                ? ['request_check_url' => 'https://api.example/check/wp-upload-pagination']
                : [
                    'status' => 'complete',
                    'success' => true,
                    'markdown' => $markdown,
                    'images' => ['page-7.png' => base64_encode('PNG page seven preview')],
                    'metadata' => ['wordpress_queue' => 'editorial-import', 'pages' => 2],
                ];
        },
        'wp-import-api-key',
        'https://api.example/marker'
    );

    $review = $adapter->serverUploadPaginationReview($upload, $params, $response, $requests, $uploadRoot, false);
    $reviewJson = json_encode($review, JSON_THROW_ON_ERROR);

    $payload = [
        'scenario' => 'wordpress-marker-runtime-convert-server-upload-pagination-currentbase',
        'purpose' => 'Review marker_server.py /marker/upload paginated conversion success for a WordPress PDF import endpoint without retaining uploaded PDF bytes or executing FastAPI, Uvicorn, live HTTP, Python, models, or external PDF tools.',
        'source' => 'sddai/markerPDF marker_server.py::convert_pdf_upload plus convert_pdf_from_upload and marker.postprocessors.markdown::get_full_text',
        'selected_route' => $review['selected_route'],
        'uploaded_filename' => $review['upload']['filename'],
        'upload_removed' => $review['upload']['upload_removed'],
        'raw_upload_bytes_excluded' => !str_contains($reviewJson, 'wordpress server upload pagination source bytes'),
        'api_key_excluded' => !str_contains($reviewJson, 'wp-import-api-key'),
        'image_payload_excluded' => !str_contains($reviewJson, base64_encode('PNG page seven preview')),
        'request_methods' => $review['request_trace']['methods'],
        'remote_fields' => $review['request_trace']['post']['fields'] ?? [],
        'page_sequence' => $review['pagination']['page_sequence'],
        'page_segment_text' => $review['pagination']['page_segment_text'],
        'segments_exclude_page_markers' => $review['pagination']['strips_markers_from_page_segments'],
        'wordpress_queue_metadata_preserved' => ($response['metadata']['wordpress_queue'] ?? null) === 'editorial-import',
        'review_only' => $review['review_only'],
        'executes_fastapi' => $review['executes_fastapi'],
        'executes_uvicorn' => $review['executes_uvicorn'],
        'executes_live_http' => $review['executes_live_http'],
        'executes_python_or_models' => $review['executes_python_or_models'],
        'executes_external_pdf_tools' => $review['executes_external_pdf_tools'],
    ];

    if (($response['success'] ?? null) !== true) {
        throw new RuntimeException('Expected marker server upload pagination conversion to succeed.');
    }
    if ($payload['request_methods'] !== ['POST', 'GET']) {
        throw new RuntimeException('Expected marker server upload pagination to perform one POST and one poll.');
    }
    if (!$payload['upload_removed'] || !$payload['raw_upload_bytes_excluded'] || !$payload['api_key_excluded'] || !$payload['image_payload_excluded']) {
        throw new RuntimeException('Expected marker server upload pagination review to exclude upload bytes, API key, and image payloads.');
    }
    if ($payload['remote_fields']['paginate'] !== true || $payload['remote_fields']['extract_images'] !== false) {
        throw new RuntimeException('Expected marker server upload pagination flags to reach the remote multipart fields.');
    }
    if ($payload['page_sequence'] !== [7, 8] || $payload['page_segment_text'] !== ["WordPress import page seven.", "WordPress import page eight."]) {
        throw new RuntimeException('Expected marker server upload pagination review to preserve page sequence and segment text.');
    }

    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($root);
}
