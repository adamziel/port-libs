<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerServerAdapter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$adapter = new MarkerServerAdapter();
$pdfPath = sys_get_temp_dir() . '/markerpdf-server-output-pagination-' . bin2hex(random_bytes(4)) . '.pdf';
if (file_put_contents($pdfPath, '%PDF paginated remote output smoke') === false) {
    throw new RuntimeException('Unable to write markerPDF paginated server output smoke fixture.');
}

$separator = MarkerServerAdapter::UPSTREAM_PAGE_SEPARATOR;
$markdown = "\n\n{1}" . $separator . "\n\nEditorial page one."
    . "\n\n{2}" . $separator . "\n\nEditorial page two.";
$requests = [];
$client = static function (string $method, string $url, array $request) use (&$requests, $markdown): array {
    $requests[] = ['method' => $method, 'url' => $url, 'files' => $request['files'] ?? []];

    return $method === 'POST'
        ? ['request_check_url' => 'https://api.example/marker/check/paginated-output']
        : [
            'status' => 'complete',
            'success' => true,
            'markdown' => $markdown,
            'metadata' => ['wordpress_queue' => 'editorial-import'],
        ];
};

try {
    $response = $adapter->convertPdfRemote(
        [
            'filepath' => $pdfPath,
            'max_pages' => 2,
            'langs' => 'English',
            'force_ocr' => false,
            'paginate' => true,
            'extract_images' => false,
        ],
        $client,
        'wp-import-api-key',
        'https://api.example/marker',
        maxPolls: 2
    );
    $plan = $adapter->serverOutputPaginationPlan($response['markdown'], true);
    $summary = $response['metadata']['server_output_pagination'] ?? [];

    $payload = [
        'scenario' => 'wordpress-marker-runtime-convert-server-output-pagination-boundary-currentbase',
        'purpose' => 'Preserve marker_server.py paginated conversion output page boundaries for WordPress PDF import review without FastAPI, Uvicorn, live HTTP, Python, models, or external PDF tools.',
        'source' => 'sddai/markerPDF marker_server.py::convert_pdf_remote plus marker.postprocessors.markdown::get_full_text',
        'request_methods' => array_column($requests, 'method'),
        'remote_paginate' => $requests[0]['files']['paginate'] ?? null,
        'remote_extract_images' => $requests[0]['files']['extract_images'] ?? null,
        'markdown_keeps_upstream_page_markers' => str_contains($response['markdown'], '{1}' . $separator)
            && str_contains($response['markdown'], '{2}' . $separator),
        'metadata_page_count' => $summary['page_count'] ?? null,
        'metadata_page_sequence' => $summary['page_sequence'] ?? [],
        'metadata_markers_review_only' => ($summary['review_only'] ?? false) === true,
        'plan_segment_text' => array_column($plan['page_segments'], 'text'),
        'plan_segments_exclude_markers' => $plan['strips_markers_from_page_segments'],
        'wordpress_queue_metadata_preserved' => ($response['metadata']['wordpress_queue'] ?? null) === 'editorial-import',
        'executes_fastapi' => false,
        'executes_uvicorn' => false,
        'executes_live_http' => false,
        'executes_python_or_models' => false,
        'executes_external_pdf_tools' => false,
    ];

    if ($payload['request_methods'] !== ['POST', 'GET']) {
        throw new RuntimeException('Expected one remote request and one poll request.');
    }
    if ($payload['remote_paginate'] !== true || $payload['remote_extract_images'] !== false) {
        throw new RuntimeException('Expected marker server remote request to forward pagination and image flags.');
    }
    if (!$payload['markdown_keeps_upstream_page_markers'] || $payload['metadata_page_count'] !== 2) {
        throw new RuntimeException('Expected paginated marker server output metadata for two pages.');
    }
    if (!$payload['plan_segments_exclude_markers'] || $payload['plan_segment_text'] !== ["Editorial page one.", "Editorial page two."]) {
        throw new RuntimeException('Expected page segment review text to exclude upstream pagination markers.');
    }
    if (!$payload['wordpress_queue_metadata_preserved']) {
        throw new RuntimeException('Expected marker server metadata to survive pagination review decoration.');
    }

    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    if (is_file($pdfPath)) {
        unlink($pdfPath);
    }
}
