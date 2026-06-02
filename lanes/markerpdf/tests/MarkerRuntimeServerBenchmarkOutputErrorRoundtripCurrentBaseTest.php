<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BenchmarkRunner;
use PortLibs\MarkerPDF\MarkerServerAdapter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-server-benchmark-roundtrip-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf server benchmark roundtrip folder.');
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
    'roundtrips marker server upload errors through benchmark output artifact JSON' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $uploadRoot = $root . DIRECTORY_SEPARATOR . 'uploads';
            $artifactPath = $root . DIRECTORY_SEPARATOR . 'benchmark-output' . DIRECTORY_SEPARATOR . 'server-error.json';
            mkdir(dirname($artifactPath), 0777, true);

            $requests = [];
            $serverResponse = (new MarkerServerAdapter())->convertPdfFromUpload(
                [
                    'filename' => 'roundtrip-server-error.pdf',
                    'content_type' => 'application/pdf',
                    'bytes' => '%PDF-1.4 server error roundtrip',
                ],
                [
                    'max_pages' => 2,
                    'langs' => 'English',
                    'force_ocr' => true,
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
                'wp-benchmark-key',
                'https://api.example/marker'
            );

            $uploadPath = $uploadRoot . DIRECTORY_SEPARATOR . 'roundtrip-server-error.pdf';
            $t->same(false, $serverResponse['success']);
            $t->contains('request_check_url', $serverResponse['error']);
            $t->same(false, is_file($uploadPath));
            $t->same([['method' => 'POST', 'url' => 'https://api.example/marker', 'filename' => 'roundtrip-server-error.pdf']], $requests);

            $runner = new BenchmarkRunner();
            $artifact = $runner->writeServerBenchmarkErrorArtifactJson(
                $artifactPath,
                $serverResponse,
                [
                    'phase' => 'server_upload',
                    'method' => 'marker',
                    'document' => 'roundtrip-server-error.pdf',
                    'benchmark_index' => 0,
                    'markdown_output_folder' => $root . DIRECTORY_SEPARATOR . 'markdown',
                    'report_output' => $root . DIRECTORY_SEPARATOR . 'overall.json',
                    'upload_removed' => !is_file($uploadPath),
                    'request_count' => count($requests),
                ]
            );

            $t->same($artifactPath, $artifact['path']);
            $t->same('server-error.json', $artifact['filename']);
            $t->same('json', $artifact['format']);
            $t->same('markerpdf.server_benchmark_error.v1', $artifact['schema']);
            $t->same('error', $artifact['status']);
            $t->same($serverResponse['error'], $artifact['error']);
            $t->same(false, $artifact['success_report_written']);
            $t->same(true, $artifact['review_only']);
            $t->true($artifact['size'] > 400);
            $t->same(hash_file('sha256', $artifactPath), $artifact['sha256']);

            $roundtrip = $runner->readServerBenchmarkErrorArtifactJson($artifactPath);
            $payload = $roundtrip['payload'];
            $t->same($artifactPath, $roundtrip['path']);
            $t->same($artifact['sha256'], $roundtrip['sha256']);
            $t->same($artifact['size'], $roundtrip['size']);
            $t->same(true, $roundtrip['roundtrip_preserves_server_error']);
            $t->same('markerpdf.server_benchmark_error.v1', $payload['schema']);
            $t->same('sddai/markerPDF marker_server.py + benchmarks/overall.py + marker/output.py', $payload['source']);
            $t->same(false, $payload['success']);
            $t->same(false, $payload['server_response']['success']);
            $t->same($serverResponse['error'], $payload['server_response']['error']);
            $t->same('server_upload', $payload['context']['phase']);
            $t->same('marker', $payload['context']['method']);
            $t->same('roundtrip-server-error.pdf', $payload['context']['document']);
            $t->same(0, $payload['context']['benchmark_index']);
            $t->same(true, $payload['context']['upload_removed']);
            $t->same(1, $payload['context']['request_count']);
            $t->same('Marker server benchmark output failed: ' . $serverResponse['error'], $payload['message_line']);
            $t->same(false, $payload['writes_markdown_after_failure']);
            $t->same(false, $payload['success_report_written']);
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
    'rejects non-error server payloads and malformed roundtrip artifacts' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $runner = new BenchmarkRunner();
            $artifactPath = $root . DIRECTORY_SEPARATOR . 'server-error.json';

            $t->throws(
                InvalidArgumentException::class,
                static fn () => $runner->writeServerBenchmarkErrorArtifactJson($artifactPath, ['success' => true, 'error' => null])
            );
            $t->throws(
                InvalidArgumentException::class,
                static fn () => $runner->writeServerBenchmarkErrorArtifactJson($artifactPath, ['success' => false])
            );

            file_put_contents($artifactPath, json_encode([
                'schema' => 'markerpdf.other.v1',
                'success' => false,
                'error' => 'wrong schema',
                'review_only' => true,
            ], JSON_THROW_ON_ERROR));

            $t->throws(
                InvalidArgumentException::class,
                static fn () => $runner->readServerBenchmarkErrorArtifactJson($artifactPath)
            );
        } finally {
            $removeTree($root);
        }
    },
];
