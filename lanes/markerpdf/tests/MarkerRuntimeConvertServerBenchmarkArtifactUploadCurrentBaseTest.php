<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BenchmarkRunner;
use PortLibs\MarkerPDF\MarkerServerAdapter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-server-benchmark-upload-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf server benchmark upload folder.');
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
    'roundtrips successful marker server uploads through benchmark artifact JSON' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $uploadRoot = $root . DIRECTORY_SEPARATOR . 'uploads';
            $artifactPath = $root . DIRECTORY_SEPARATOR . 'benchmark-output' . DIRECTORY_SEPARATOR . 'server-upload.json';
            mkdir(dirname($artifactPath), 0777, true);

            $requests = [];
            $poll = 0;
            $serverResponse = (new MarkerServerAdapter())->convertPdfFromUpload(
                [
                    'filename' => '../benchmark artifact upload.pdf',
                    'content_type' => 'application/pdf',
                    'bytes' => "%PDF-1.4\n% uploaded benchmark source bytes\n%%EOF",
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
                static function (string $method, string $url, array $request) use (&$requests, &$poll): array {
                    $requests[] = [
                        'method' => $method,
                        'url' => $url,
                        'request' => $request,
                    ];

                    if ($method === 'POST') {
                        return ['request_check_url' => 'https://api.example/check/server-upload'];
                    }

                    $poll++;

                    return [
                        'status' => 'complete',
                        'success' => true,
                        'markdown' => "# Uploaded benchmark document\n\nFirst page text.\n\nSecond page text.",
                        'images' => ['page-1.png' => base64_encode('PNG artifact bytes')],
                        'metadata' => [
                            'pages' => 2,
                            'source' => 'marker_server_upload',
                            'benchmark_method' => 'marker',
                        ],
                    ];
                },
                'wp-benchmark-upload-key',
                'https://api.example/marker'
            );

            $uploadPath = $uploadRoot . DIRECTORY_SEPARATOR . 'benchmark artifact upload.pdf';
            $t->same(true, $serverResponse['success']);
            $t->same(false, is_file($uploadPath));
            $t->same(['POST', 'GET'], array_column($requests, 'method'));
            $t->same('benchmark artifact upload.pdf', $requests[0]['request']['files']['file']['filename']);
            $t->contains('uploaded benchmark source bytes', $requests[0]['request']['files']['file']['bytes']);

            $runner = new BenchmarkRunner();
            $artifact = $runner->writeServerBenchmarkUploadArtifactJson(
                $artifactPath,
                $serverResponse,
                [
                    'phase' => 'server_upload_success',
                    'method' => 'marker',
                    'document' => 'benchmark artifact upload.pdf',
                    'benchmark_index' => 0,
                    'markdown_output_folder' => $root . DIRECTORY_SEPARATOR . 'markdown',
                    'markdown_output' => $root . DIRECTORY_SEPARATOR . 'markdown' . DIRECTORY_SEPARATOR . 'marker_benchmark_artifact_upload.md',
                    'report_output' => $root . DIRECTORY_SEPARATOR . 'overall.json',
                    'uploaded_filename' => 'benchmark artifact upload.pdf',
                    'server_route' => 'remote',
                    'upload_removed' => !is_file($uploadPath),
                    'request_count' => count($requests),
                    'pages' => 2,
                    'score' => 0.97,
                    'time' => 0.25,
                    'success_report_written' => true,
                ]
            );

            $t->same($artifactPath, $artifact['path']);
            $t->same('server-upload.json', $artifact['filename']);
            $t->same('json', $artifact['format']);
            $t->same('markerpdf.server_benchmark_upload.v1', $artifact['schema']);
            $t->same('success', $artifact['status']);
            $t->same(true, $artifact['success_report_written']);
            $t->same(true, $artifact['review_only']);
            $t->same(hash('sha256', $serverResponse['markdown']), $artifact['markdown_sha256']);
            $t->true($artifact['size'] > 900);
            $t->same(hash_file('sha256', $artifactPath), $artifact['sha256']);

            $artifactJson = (string) file_get_contents($artifactPath);
            $t->true(!str_contains($artifactJson, 'uploaded benchmark source bytes'));
            $t->true(!str_contains($artifactJson, base64_encode('PNG artifact bytes')));

            $roundtrip = $runner->readServerBenchmarkUploadArtifactJson($artifactPath);
            $payload = $roundtrip['payload'];
            $summary = $payload['server_response'];
            $context = $payload['context'];

            $t->same($artifactPath, $roundtrip['path']);
            $t->same('markerpdf.server_benchmark_upload.v1', $payload['schema']);
            $t->same('sddai/markerPDF marker_server.py + benchmarks/overall.py + marker/output.py', $payload['source']);
            $t->same('success', $payload['status']);
            $t->same(true, $payload['success']);
            $t->same(true, $summary['success']);
            $t->same(hash('sha256', $serverResponse['markdown']), $summary['markdown_sha256']);
            $t->same(strlen($serverResponse['markdown']), $summary['markdown_byte_length']);
            $t->same($serverResponse['markdown'], $summary['markdown']);
            $t->same(2, $summary['metadata']['pages']);
            $t->same(['benchmark_method', 'pages', 'source'], $summary['metadata_keys']);
            $t->same(1, $summary['image_count']);
            $t->same('page-1.png', $summary['images'][0]['filename']);
            $t->same(strlen(base64_encode('PNG artifact bytes')), $summary['images'][0]['base64_length']);
            $t->same(hash('sha256', base64_encode('PNG artifact bytes')), $summary['images'][0]['base64_sha256']);
            $t->same(strlen('PNG artifact bytes'), $summary['images'][0]['decoded_size']);
            $t->same(hash('sha256', 'PNG artifact bytes'), $summary['images'][0]['decoded_sha256']);
            $t->same(true, $summary['images_are_summarized']);
            $t->same('server_upload_success', $context['phase']);
            $t->same('marker', $context['method']);
            $t->same('benchmark artifact upload.pdf', $context['document']);
            $t->same('benchmark artifact upload.pdf', $context['uploaded_filename']);
            $t->same('remote', $context['server_route']);
            $t->same(true, $context['upload_removed']);
            $t->same(2, $context['request_count']);
            $t->same(2, $context['pages']);
            $t->same(0.97, $context['score']);
            $t->same(0.25, $context['time']);
            $t->same(true, $context['success_report_written']);
            $t->same('Marker server benchmark upload completed for marker/benchmark artifact upload.pdf', $payload['message_line']);
            $t->same(true, $payload['default_server_upload_removes_temp_file']);
            $t->same(true, $payload['default_benchmark_writes_markdown_on_success']);
            $t->same(true, $payload['default_benchmark_writes_report_on_success']);
            $t->same(true, $payload['excludes_uploaded_pdf_bytes']);
            $t->same(true, $roundtrip['roundtrip_preserves_server_success']);
            $t->same(true, $roundtrip['roundtrip_preserves_markdown_hash']);
            $t->same(false, $payload['executes_fastapi']);
            $t->same(false, $payload['executes_uvicorn']);
            $t->same(false, $payload['executes_live_http']);
            $t->same(false, $payload['executes_external_tools']);
            $t->same(false, $payload['executes_python_or_models']);
            $t->same(true, $payload['review_only']);
        } finally {
            $removeTree($root);
        }
    },
    'rejects failed or malformed server upload benchmark artifacts' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $runner = new BenchmarkRunner();
            $artifactPath = $root . DIRECTORY_SEPARATOR . 'server-upload.json';

            $t->throws(
                InvalidArgumentException::class,
                static fn () => $runner->writeServerBenchmarkUploadArtifactJson($artifactPath, ['success' => false, 'error' => 'server failed'])
            );
            $t->throws(
                InvalidArgumentException::class,
                static fn () => $runner->writeServerBenchmarkUploadArtifactJson($artifactPath, ['success' => true, 'metadata' => []])
            );

            file_put_contents($artifactPath, json_encode([
                'schema' => 'markerpdf.server_benchmark_upload.v1',
                'success' => true,
                'status' => 'success',
                'server_response' => [
                    'success' => true,
                    'markdown' => 'tampered',
                    'markdown_sha256' => hash('sha256', 'original'),
                    'images_are_summarized' => true,
                ],
                'review_only' => true,
            ], JSON_THROW_ON_ERROR));

            $t->throws(
                InvalidArgumentException::class,
                static fn () => $runner->readServerBenchmarkUploadArtifactJson($artifactPath)
            );
        } finally {
            $removeTree($root);
        }
    },
];
