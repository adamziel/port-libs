<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerServerAdapter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-upload-pagination-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf upload pagination test folder.');
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
    'plans upload pagination fields and returns remote upload errors while cleaning temporary PDFs' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $uploadRoot = $makeTempDir();
        try {
            $adapter = new MarkerServerAdapter();
            $params = [
                'max_pages' => '3',
                'langs' => 'English,Spanish',
                'force_ocr' => 'true',
                'paginate' => 'on',
                'extract_images' => '0',
            ];
            $plan = $adapter->uploadRoutePlan($params, $uploadRoot, local: false);

            $t->same('/marker/upload', $plan['endpoint']);
            $t->same('POST', $plan['method']);
            $t->same('application/pdf', $plan['file_field']['media_type']);
            $t->same(400, $plan['file_field']['invalid_content_type_status_code']);
            $t->same('Only PDF files are allowed.', $plan['file_field']['invalid_content_type_detail']);
            $t->same(true, $plan['file_field']['rejects_non_pdf_before_guard']);
            $t->same('remote', $plan['selected_route']);
            $t->same(3, $plan['form_params']['max_pages']['value']);
            $t->same('English,Spanish', $plan['form_params']['langs']['value']);
            $t->same(true, $plan['form_params']['force_ocr']['value']);
            $t->same(true, $plan['form_params']['paginate']['value']);
            $t->same(false, $plan['form_params']['extract_images']['value']);
            $t->same(false, $plan['local_route']['applies_direct_marker_option_guard']);
            $t->same(false, $plan['local_route']['paginate_forwarded_to_convert_single_pdf']);
            $t->same(false, $plan['local_route']['extract_images_forwarded_to_convert_single_pdf']);
            $t->same(['file', 'max_pages', 'langs', 'force_ocr', 'paginate', 'extract_images'], $plan['remote_route']['forwards_multipart_fields']);
            $t->same([
                'max_pages' => 3,
                'langs' => 'English,Spanish',
                'force_ocr' => true,
                'paginate' => true,
                'extract_images' => false,
            ], $plan['remote_route']['multipart_field_values']);
            $t->same(true, $plan['error_boundary']['body_errors_return_success_false']);
            $t->same(true, $plan['error_boundary']['missing_request_check_url_becomes_upload_error_payload']);
            $t->same(true, $plan['cleanup']['removes_upload_after_body_error']);
            $t->same(false, $plan['executes_fastapi']);
            $t->same(false, $plan['executes_live_http']);
            $t->same(false, $plan['executes_python_or_models']);

            $requests = [];
            $response = $adapter->convertPdfFromUpload(
                [
                    'filename' => 'paginated-report.pdf',
                    'content_type' => 'application/pdf',
                    'bytes' => '%PDF paginated remote upload',
                ],
                $params,
                $uploadRoot,
                false,
                static fn (): string => 'unused-local-converter',
                static function (string $method, string $url, array $request) use (&$requests): array {
                    $requests[] = ['method' => $method, 'url' => $url, 'request' => $request];

                    return ['status' => 'queued-without-request-check-url'];
                },
                'wp-import-key',
                'https://api.example/marker'
            );

            $t->same(false, $response['success']);
            $t->contains('request_check_url', $response['error']);
            $t->same(['POST'], array_column($requests, 'method'));
            $files = $requests[0]['request']['files'];
            $t->same('paginated-report.pdf', $files['file']['filename']);
            $t->same('%PDF paginated remote upload', $files['file']['bytes']);
            $t->same(3, $files['max_pages']);
            $t->same('English,Spanish', $files['langs']);
            $t->same(true, $files['force_ocr']);
            $t->same(true, $files['paginate']);
            $t->same(false, $files['extract_images']);
            $t->same(false, is_file($uploadRoot . DIRECTORY_SEPARATOR . 'paginated-report.pdf'));
        } finally {
            $removeTree($uploadRoot);
        }
    },
];
