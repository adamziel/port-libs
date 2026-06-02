<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerServerAdapter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-server-upload-pagination-currentbase-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf server upload pagination folder.');
    }

    return $path;
};

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

return [
    'reviews successful remote upload pagination without retaining uploaded PDF bytes' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $uploadRoot = $makeTempDir();
        try {
            $separator = MarkerServerAdapter::UPSTREAM_PAGE_SEPARATOR;
            $markdown = "\n\n{4}" . $separator . "\n\nUpload page four.\n\n"
                . "\n\n{5}" . $separator . "\n\nUpload page five.";
            $upload = [
                'filename' => '../wp paginated upload.pdf',
                'content_type' => 'application/pdf',
                'bytes' => "%PDF-1.7\nwordpress paginated upload source bytes\n%%EOF",
            ];
            $params = [
                'max_pages' => '2',
                'langs' => 'English,French',
                'force_ocr' => 'yes',
                'paginate' => 'on',
                'extract_images' => '0',
            ];
            $requests = [];
            $client = static function (string $method, string $url, array $request) use (&$requests, $markdown): array {
                $requests[] = ['method' => $method, 'url' => $url, 'request' => $request];

                return $method === 'POST'
                    ? ['request_check_url' => 'https://api.example/check/upload-pagination']
                    : [
                        'status' => 'complete',
                        'success' => true,
                        'markdown' => $markdown,
                        'images' => ['page-4.png' => base64_encode('PNG page four preview')],
                        'metadata' => ['source' => 'remote-upload', 'pages' => 2],
                    ];
            };

            $adapter = new MarkerServerAdapter();
            $response = $adapter->convertPdfFromUpload(
                $upload,
                $params,
                $uploadRoot,
                false,
                static fn (): string => 'unused-local-converter',
                $client,
                'wp-secret-upload-key',
                'https://api.example/marker',
            );
            $uploadPath = $uploadRoot . DIRECTORY_SEPARATOR . 'wp paginated upload.pdf';
            $review = $adapter->serverUploadPaginationReview(
                $upload,
                $params,
                $response,
                $requests,
                $uploadRoot,
                false
            );

            $t->same(true, $response['success']);
            $t->same(false, is_file($uploadPath));
            $t->same($markdown, $response['markdown']);
            $t->same(['POST', 'GET'], array_column($requests, 'method'));
            $t->same('wp paginated upload.pdf', $requests[0]['request']['files']['file']['filename']);
            $t->contains('wordpress paginated upload source bytes', $requests[0]['request']['files']['file']['bytes']);
            $t->same(true, isset($response['metadata']['server_output_pagination']));

            $t->same('markerpdf.server_upload_pagination_review.v1', $review['schema']);
            $t->contains('marker_server.py::convert_pdf_upload', $review['source']);
            $t->contains('marker.postprocessors.markdown::get_full_text', $review['source']);
            $t->same('/marker/upload', $review['endpoint']);
            $t->same('POST', $review['method']);
            $t->same('remote', $review['selected_route']);
            $t->same('wp paginated upload.pdf', $review['upload']['filename']);
            $t->same('application/pdf', $review['upload']['content_type']);
            $t->same(strlen($upload['bytes']), $review['upload']['byte_length']);
            $t->same(hash('sha256', $upload['bytes']), $review['upload']['sha256']);
            $t->same(true, $review['upload']['raw_bytes_excluded']);
            $t->same($uploadPath, $review['upload']['temporary_path']);
            $t->same(false, $review['upload']['temporary_upload_exists']);
            $t->same(true, $review['upload']['upload_removed']);

            $t->same(2, $review['form_params']['max_pages']);
            $t->same('English,French', $review['form_params']['langs']);
            $t->same(true, $review['form_params']['force_ocr']);
            $t->same(true, $review['form_params']['paginate']);
            $t->same(false, $review['form_params']['extract_images']);
            $t->same(['file', 'max_pages', 'langs', 'force_ocr', 'paginate', 'extract_images'], $review['route_plan']['remote_fields']);
            $t->same($review['form_params'], $review['route_plan']['remote_values']);
            $t->same(true, $review['route_plan']['local_uses_uploaded_filepath']);
            $t->same(false, $review['route_plan']['local_applies_direct_marker_option_guard']);
            $t->same(true, $review['route_plan']['cleanup']['removes_upload_after_success']);
            $t->same(true, $review['route_plan']['cleanup']['removes_upload_after_body_error']);

            $trace = $review['request_trace'];
            $t->same(2, $trace['request_count']);
            $t->same(['POST', 'GET'], $trace['methods']);
            $t->same(1, $trace['post_count']);
            $t->same(1, $trace['poll_count']);
            $t->same('https://api.example/marker', $trace['post']['url']);
            $t->same('wp paginated upload.pdf', $trace['post']['file']['filename']);
            $t->same('application/pdf', $trace['post']['file']['content_type']);
            $t->same(strlen($upload['bytes']), $trace['post']['file']['byte_length']);
            $t->same(hash('sha256', $upload['bytes']), $trace['post']['file']['sha256']);
            $t->same(true, $trace['post']['file']['raw_bytes_excluded']);
            $t->same(2, $trace['post']['fields']['max_pages']);
            $t->same('English,French', $trace['post']['fields']['langs']);
            $t->same(true, $trace['post']['fields']['force_ocr']);
            $t->same(true, $trace['post']['fields']['paginate']);
            $t->same(false, $trace['post']['fields']['extract_images']);
            $t->same(['https://api.example/check/upload-pagination'], $trace['poll_urls']);
            $t->same(true, $trace['headers_excluded']);
            $t->same(true, $trace['api_key_excluded']);

            $t->same(true, $review['response']['success']);
            $t->same('complete', $review['response']['status']);
            $t->same(hash('sha256', $markdown), $review['response']['markdown_sha256']);
            $t->same(strlen($markdown), $review['response']['markdown_byte_length']);
            $t->same(['pages', 'server_output_pagination', 'source'], $review['response']['metadata_keys']);
            $t->same(true, $review['response']['has_server_output_pagination_metadata']);
            $t->same(1, $review['response']['image_count']);
            $t->same(false, $review['response']['markdown_contains_uploaded_pdf_bytes']);

            $pagination = $review['pagination'];
            $t->same(true, $pagination['paginate_requested']);
            $t->same(true, $pagination['has_upstream_markers']);
            $t->same(true, $pagination['markdown_starts_with_page_marker']);
            $t->same(2, $pagination['page_count']);
            $t->same(4, $pagination['first_page']);
            $t->same(5, $pagination['last_page']);
            $t->same([4, 5], $pagination['page_sequence']);
            $t->same(true, $pagination['monotonic_page_sequence']);
            $t->same(0, $pagination['page_markers'][0]['offset']);
            $t->same(4, $pagination['page_markers'][0]['page']);
            $t->same(5, $pagination['page_markers'][1]['page']);
            $t->same(hash('sha256', "Upload page four.\n\n"), $pagination['page_segments'][0]['text_sha256']);
            $t->same(hash('sha256', "\n\nUpload page four.\n\n"), $pagination['page_segments'][0]['raw_text_sha256']);
            $t->same(["Upload page four.\n\n", 'Upload page five.'], $pagination['page_segment_text']);
            $t->same(true, $pagination['strips_markers_from_page_segments']);
            $t->same(true, $pagination['review_only']);

            $t->same(true, $review['default_server_upload_removes_temp_file']);
            $t->same(true, $review['excludes_uploaded_pdf_bytes']);
            $t->same(true, $review['excludes_api_key_headers']);
            $t->same(false, $review['executes_fastapi']);
            $t->same(false, $review['executes_uvicorn']);
            $t->same(false, $review['executes_live_http']);
            $t->same(false, $review['executes_python_or_models']);
            $t->same(false, $review['executes_external_pdf_tools']);

            $reviewJson = json_encode($review, JSON_THROW_ON_ERROR);
            $t->true(!str_contains($reviewJson, 'wordpress paginated upload source bytes'));
            $t->true(!str_contains($reviewJson, 'wp-secret-upload-key'));
            $t->true(!str_contains($reviewJson, base64_encode('PNG page four preview')));
        } finally {
            $removeTree($uploadRoot);
        }
    },
    'reviews local upload pagination while preserving the upstream upload-route guard boundary' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $uploadRoot = $makeTempDir();
        try {
            $separator = MarkerServerAdapter::UPSTREAM_PAGE_SEPARATOR;
            $markdown = "\n\n{1}" . $separator . "\n\nLocal upload page one."
                . "\n\n{2}" . $separator . "\n\nLocal upload page two.";
            $upload = [
                'filename' => 'local-pagination.pdf',
                'content_type' => 'application/pdf',
                'bytes' => '%PDF local paginated upload bytes',
            ];
            $params = [
                'max_pages' => 2,
                'langs' => 'English',
                'force_ocr' => true,
                'paginate' => true,
                'extract_images' => false,
            ];
            $seenOptions = null;
            $adapter = new MarkerServerAdapter();
            $response = $adapter->convertPdfFromUpload(
                $upload,
                $params,
                $uploadRoot,
                true,
                static function (string $filepath, array $options) use (&$seenOptions, $markdown): array {
                    $seenOptions = $options;

                    return [
                        'markdown' => $markdown,
                        'images' => [],
                        'metadata' => ['source' => basename($filepath)],
                    ];
                }
            );
            $review = $adapter->serverUploadPaginationReview($upload, $params, $response, [], $uploadRoot, true);

            $t->same(true, $response['success']);
            $t->same(['max_pages' => 2, 'langs' => 'English', 'ocr_all_pages' => true], $seenOptions);
            $t->same('local', $review['selected_route']);
            $t->same(true, $review['route_plan']['local_uses_uploaded_filepath']);
            $t->same(false, $review['route_plan']['local_applies_direct_marker_option_guard']);
            $t->same(0, $review['request_trace']['request_count']);
            $t->same([], $review['request_trace']['methods']);
            $t->same(null, $review['request_trace']['post']);
            $t->same([], $review['request_trace']['poll_urls']);
            $t->same(true, $review['upload']['upload_removed']);
            $t->same(true, $review['pagination']['paginate_requested']);
            $t->same([1, 2], $review['pagination']['page_sequence']);
            $t->same(['Local upload page one.', 'Local upload page two.'], $review['pagination']['page_segment_text']);
            $t->same(true, $review['response']['has_server_output_pagination_metadata']);
            $t->same(false, $review['response']['markdown_contains_uploaded_pdf_bytes']);
            $t->same(false, $review['executes_fastapi']);
            $t->same(false, $review['executes_python_or_models']);

            $t->throws(
                InvalidArgumentException::class,
                static fn (): array => (new MarkerServerAdapter())->serverUploadPaginationReview(
                    ['filename' => 'bad.txt', 'content_type' => 'text/plain', 'bytes' => 'not pdf'],
                    [],
                    ['success' => true, 'markdown' => 'unused']
                )
            );
        } finally {
            $removeTree($uploadRoot);
        }
    },
];
