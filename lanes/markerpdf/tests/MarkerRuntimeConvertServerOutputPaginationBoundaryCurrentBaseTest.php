<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerServerAdapter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-server-output-pagination-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf server output pagination test folder.');
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
    'records upstream page separators on completed remote conversion output' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $pdfPath = $root . DIRECTORY_SEPARATOR . 'remote-pagination.pdf';
            file_put_contents($pdfPath, '%PDF paginated remote output');

            $separator = MarkerServerAdapter::UPSTREAM_PAGE_SEPARATOR;
            $firstPage = "\n\n{2}" . $separator . "\n\nRemote page two import.\n\n";
            $secondPage = "\n\n{3}" . $separator . "\n\nRemote page three import.";
            $markdown = $firstPage . $secondPage;
            $requests = [];
            $client = static function (string $method, string $url, array $request) use (&$requests, $markdown): array {
                $requests[] = ['method' => $method, 'url' => $url, 'request' => $request];

                return $method === 'POST'
                    ? ['request_check_url' => 'https://api.example/marker/check/paginated']
                    : [
                        'status' => 'complete',
                        'success' => true,
                        'markdown' => $markdown,
                        'metadata' => ['source' => 'remote-server'],
                    ];
            };

            $adapter = new MarkerServerAdapter();
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
                'remote-key',
                'https://api.example/marker',
                maxPolls: 2
            );

            $t->same(['POST', 'GET'], array_column($requests, 'method'));
            $t->same(true, $requests[0]['request']['files']['paginate']);
            $t->same(false, $requests[0]['request']['files']['extract_images']);
            $t->same($markdown, $response['markdown']);
            $t->contains('{2}' . $separator, $response['markdown']);
            $t->contains('{3}' . $separator, $response['markdown']);
            $t->same('remote-server', $response['metadata']['source']);

            $summary = $response['metadata']['server_output_pagination'];
            $t->same(true, $summary['paginate_requested']);
            $t->same(true, $summary['has_upstream_markers']);
            $t->same(true, $summary['markdown_starts_with_page_marker']);
            $t->same(2, $summary['page_count']);
            $t->same(2, $summary['first_page']);
            $t->same(3, $summary['last_page']);
            $t->same([2, 3], $summary['page_sequence']);
            $t->same(true, $summary['monotonic_page_sequence']);
            $t->same(0, $summary['page_markers'][0]['offset']);
            $t->same(strlen($firstPage), $summary['page_markers'][1]['offset']);
            $t->same(2, $summary['page_segments'][0]['page']);
            $t->same(hash('sha256', "Remote page two import.\n\n"), $summary['page_segments'][0]['text_sha256']);
            $t->same(hash('sha256', "\n\nRemote page two import.\n\n"), $summary['page_segments'][0]['raw_text_sha256']);
            $t->same(true, $summary['strips_markers_from_page_segments']);
            $t->same(true, $summary['review_only']);
            $t->same(false, $summary['executes_live_http']);
            $t->same(false, $summary['executes_python_or_models']);

            $plan = $adapter->serverOutputPaginationPlan($markdown, true);
            $t->same($separator, $plan['upstream_page_separator']);
            $t->same("\n\n{PAGE_NUMBER}", $plan['marker_prefix']);
            $t->same([2, 3], $plan['page_sequence']);
            $t->same("Remote page two import.\n\n", $plan['page_segments'][0]['text']);
            $t->same('Remote page three import.', $plan['page_segments'][1]['text']);
            $t->same(true, $plan['strips_markers_from_page_segments']);
        } finally {
            $removeTree($root);
        }
    },
    'preserves paginated local upload output while keeping upload route option boundaries' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $uploadRoot = $makeTempDir();
        try {
            $separator = MarkerServerAdapter::UPSTREAM_PAGE_SEPARATOR;
            $markdown = "\n\n{10}" . $separator . "\n\nLocal upload page ten."
                . "\n\n{11}" . $separator . "\n\nLocal upload page eleven.";
            $seenPath = null;
            $seenOptions = null;

            $adapter = new MarkerServerAdapter();
            $response = $adapter->convertPdfFromUpload(
                [
                    'filename' => '../local-output-pagination.pdf',
                    'content_type' => 'application/pdf',
                    'bytes' => '%PDF local pagination output',
                ],
                [
                    'max_pages' => '2',
                    'langs' => 'English',
                    'force_ocr' => 'true',
                    'paginate' => 'on',
                    'extract_images' => '0',
                ],
                $uploadRoot,
                true,
                static function (string $filepath, array $options) use (&$seenPath, &$seenOptions, $markdown): array {
                    $seenPath = $filepath;
                    $seenOptions = $options;

                    return [
                        'markdown' => $markdown,
                        'images' => ['page.png' => 'PNG-PAGE'],
                        'metadata' => ['route' => 'local-upload'],
                    ];
                }
            );

            $t->same(true, $response['success']);
            $t->same($markdown, $response['markdown']);
            $t->same(['page.png' => 'UE5HLVBBR0U='], $response['images']);
            $t->same($uploadRoot . DIRECTORY_SEPARATOR . 'local-output-pagination.pdf', $seenPath);
            $t->same(['max_pages' => 2, 'langs' => 'English', 'ocr_all_pages' => true], $seenOptions);
            $t->same(false, is_file((string) $seenPath));
            $t->same('local-upload', $response['metadata']['route']);

            $summary = $response['metadata']['server_output_pagination'];
            $t->same(true, $summary['paginate_requested']);
            $t->same(2, $summary['page_count']);
            $t->same([10, 11], $summary['page_sequence']);
            $t->same(true, $summary['monotonic_page_sequence']);
            $t->same(true, $summary['strips_markers_from_page_segments']);
            $t->same(false, $summary['executes_fastapi']);
            $t->same(false, $summary['executes_uvicorn']);
            $t->same(false, $summary['executes_python_or_models']);
        } finally {
            $removeTree($uploadRoot);
        }
    },
    'keeps unmarked server output responses unchanged even when pagination was requested' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $pdfPath = $root . DIRECTORY_SEPARATOR . 'unmarked-pagination.pdf';
            file_put_contents($pdfPath, '%PDF unmarked remote output');
            $client = static function (string $method): array {
                return $method === 'POST'
                    ? ['request_check_url' => 'https://api.example/marker/check/unmarked']
                    : ['status' => 'complete', 'markdown' => '# Unmarked Remote Output', 'success' => true];
            };

            $adapter = new MarkerServerAdapter();
            $response = $adapter->convertPdfRemote(
                [
                    'filepath' => $pdfPath,
                    'max_pages' => null,
                    'langs' => null,
                    'force_ocr' => false,
                    'paginate' => true,
                    'extract_images' => true,
                ],
                $client,
                'remote-key',
                'https://api.example/marker',
                maxPolls: 1
            );

            $t->same(['status' => 'complete', 'markdown' => '# Unmarked Remote Output', 'success' => true], $response);

            $review = $adapter->serverOutputPaginationPlan("\n\n{5}not-the-upstream-separator\n\nVisible text", true);
            $t->same(false, $review['has_upstream_markers']);
            $t->same(0, $review['page_count']);
            $t->same([], $review['page_sequence']);
            $t->same([], $review['page_segments']);
            $t->same(false, $review['markdown_starts_with_page_marker']);
            $t->same(true, $review['review_only']);
        } finally {
            $removeTree($root);
        }
    },
];
