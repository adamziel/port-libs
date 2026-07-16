<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BenchmarkRunner;
use PortLibs\MarkerPDF\MarkerServerAdapter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-server-upload-benchmark-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf server upload benchmark folder.');
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
    'writes failed marker upload attempts as benchmark error artifacts without output markdown' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $uploadRoot = $root . DIRECTORY_SEPARATOR . 'uploads';
            $outputRoot = $root . DIRECTORY_SEPARATOR . 'benchmark-output';
            $errorArtifactPath = $root . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . 'server-upload-error.json';
            $requests = [];

            $serverResponse = (new MarkerServerAdapter())->convertPdfFromUpload(
                [
                    'filename' => '../server-upload-failed.pdf',
                    'content_type' => 'application/pdf',
                    'bytes' => '%PDF-1.4 upload benchmark failed remote',
                ],
                [
                    'max_pages' => 2,
                    'langs' => 'English,Spanish',
                    'force_ocr' => true,
                    'paginate' => true,
                    'extract_images' => false,
                ],
                $uploadRoot,
                false,
                static fn (): string => 'unused-local-converter',
                static function (string $method, string $url, array $request) use (&$requests): array {
                    $requests[] = [
                        'method' => $method,
                        'url' => $url,
                        'filename' => $request['files']['file']['filename'] ?? null,
                        'paginate' => $request['files']['paginate'] ?? null,
                        'extract_images' => $request['files']['extract_images'] ?? null,
                    ];

                    return ['status' => 'queued-without-request-check-url'];
                },
                'wp-upload-benchmark-key',
                'https://api.example/marker'
            );

            $uploadPath = $uploadRoot . DIRECTORY_SEPARATOR . 'server-upload-failed.pdf';
            $t->same(false, $serverResponse['success']);
            $t->contains('request_check_url', $serverResponse['error']);
            $t->same(false, is_file($uploadPath));
            $t->same([
                [
                    'method' => 'POST',
                    'url' => 'https://api.example/marker',
                    'filename' => 'server-upload-failed.pdf',
                    'paginate' => true,
                    'extract_images' => false,
                ],
            ], $requests);

            $result = (new BenchmarkRunner())->writeServerUploadBenchmarkResult(
                $outputRoot,
                $errorArtifactPath,
                '../server-upload-failed.pdf',
                $serverResponse,
                [
                    'benchmark_index' => 5,
                    'markdown_output_folder' => $outputRoot,
                    'report_output' => $root . DIRECTORY_SEPARATOR . 'overall.json',
                    'upload_removed' => !is_file($uploadPath),
                    'request_count' => count($requests),
                ]
            );

            $t->same('markerpdf.server_upload_benchmark_result.v1', $result['schema']);
            $t->same('error', $result['status']);
            $t->same(false, $result['success']);
            $t->same('error_artifact', $result['result_kind']);
            $t->same('server-upload-failed.pdf', $result['document']);
            $t->same('server_upload', $result['context']['phase']);
            $t->same('marker', $result['context']['method']);
            $t->same('server-upload-failed.pdf', $result['context']['document']);
            $t->same(5, $result['context']['benchmark_index']);
            $t->same(true, $result['context']['upload_removed']);
            $t->same(1, $result['context']['request_count']);
            $t->same(false, $result['benchmark_output_bundle_written']);
            $t->same(true, $result['error_artifact_written']);
            $t->same(false, $result['success_report_written']);
            $t->same(false, $result['writes_markdown_after_failure']);
            $t->same(null, $result['output_bundle']);
            $t->same(null, $result['output_artifacts']);
            $t->same('markerpdf.server_benchmark_error.v1', $result['error_artifact']['schema']);
            $t->same('error', $result['error_artifact']['status']);
            $t->same($errorArtifactPath, $result['error_artifact']['path']);
            $t->same($serverResponse['error'], $result['error_artifact']['error']);
            $t->same(true, $result['error_artifact']['roundtrip_preserves_server_error']);
            $t->same(hash_file('sha256', $errorArtifactPath), $result['error_artifact']['sha256']);
            $t->same(false, is_file($outputRoot . DIRECTORY_SEPARATOR . 'server-upload-failed' . DIRECTORY_SEPARATOR . 'server-upload-failed.md'));
            $t->same(false, $result['executes_fastapi']);
            $t->same(false, $result['executes_uvicorn']);
            $t->same(false, $result['executes_live_http']);
            $t->same(false, $result['executes_external_tools']);
            $t->same(false, $result['executes_python_or_models']);
            $t->same(true, $result['review_only']);
        } finally {
            $removeTree($root);
        }
    },
    'writes successful marker upload attempts as benchmark output bundles without error artifacts' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $uploadRoot = $root . DIRECTORY_SEPARATOR . 'uploads';
            $outputRoot = $root . DIRECTORY_SEPARATOR . 'benchmark-output';
            $errorArtifactPath = $root . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . 'unused-server-upload-error.json';

            $serverResponse = (new MarkerServerAdapter())->convertPdfFromUpload(
                [
                    'filename' => 'server-upload-complete.pdf',
                    'content_type' => 'application/pdf',
                    'bytes' => '%PDF-1.4 upload benchmark complete',
                ],
                [
                    'max_pages' => 1,
                    'langs' => 'English',
                    'force_ocr' => false,
                    'paginate' => true,
                    'extract_images' => true,
                ],
                $uploadRoot,
                true,
                static function (string $filepath, array $options): array {
                    return [
                        'markdown' => "\n\n{1}" . MarkerServerAdapter::UPSTREAM_PAGE_SEPARATOR
                            . "\n\nUpload benchmark complete.\n\n![Preview](../unsafe preview?.png)",
                        'images' => ['../unsafe preview?.png' => 'PNG-UPLOAD-BENCHMARK'],
                        'metadata' => [
                            'source' => basename($filepath),
                            'options' => $options,
                        ],
                    ];
                }
            );

            $uploadPath = $uploadRoot . DIRECTORY_SEPARATOR . 'server-upload-complete.pdf';
            $t->same(true, $serverResponse['success']);
            $t->same(false, is_file($uploadPath));
            $t->same(true, isset($serverResponse['metadata']['server_output_pagination']));

            $result = (new BenchmarkRunner())->writeServerUploadBenchmarkResult(
                $outputRoot,
                $errorArtifactPath,
                'server-upload-complete.pdf',
                $serverResponse,
                [
                    'benchmark_index' => 6,
                    'markdown_output_folder' => $outputRoot,
                    'report_output' => $root . DIRECTORY_SEPARATOR . 'overall.json',
                    'upload_removed' => !is_file($uploadPath),
                    'request_count' => 0,
                ]
            );

            $markdownPath = $result['output_artifacts']['markdown_artifact']['path'];
            $bundlePath = $result['output_bundle']['path'];
            $markdown = (string) file_get_contents($markdownPath);
            $bundleJson = (string) file_get_contents($bundlePath);

            $t->same('complete', $result['status']);
            $t->same(true, $result['success']);
            $t->same('output_bundle', $result['result_kind']);
            $t->same('server-upload-complete.pdf', $result['document']);
            $t->same('markerpdf.server_benchmark_output_bundle.v1', $result['output_bundle']['schema']);
            $t->same('complete', $result['output_bundle']['status']);
            $t->same(true, $result['output_bundle']['roundtrip_preserves_output_bundle']);
            $t->same(null, $result['error_artifact']);
            $t->same(true, $result['benchmark_output_bundle_written']);
            $t->same(false, $result['error_artifact_written']);
            $t->same(false, is_file($errorArtifactPath));
            $t->same(true, is_file($markdownPath));
            $t->contains('Upload benchmark complete.', $markdown);
            $t->contains('![Preview](unsafe_preview.png)', $markdown);
            $t->true(!str_contains($markdown, '../unsafe preview?.png'));
            $t->true(!str_contains($bundleJson, 'UE5HLVVQTE9BRC1CRU5DSE1BUks='));
            $t->same(true, $result['context']['upload_removed']);
            $t->same(0, $result['context']['request_count']);
            $t->same(['options', 'server_output_pagination', 'source'], $result['server_response']['metadata_keys']);
            $t->same(false, $result['executes_fastapi']);
            $t->same(false, $result['executes_uvicorn']);
            $t->same(false, $result['executes_live_http']);
            $t->same(false, $result['executes_external_tools']);
            $t->same(false, $result['executes_python_or_models']);
            $t->same(true, $result['review_only']);
        } finally {
            $removeTree($root);
        }
    },
    'rejects upload benchmark results without an explicit server success flag' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $t->throws(
                InvalidArgumentException::class,
                static fn () => (new BenchmarkRunner())->writeServerUploadBenchmarkResult(
                    $root . DIRECTORY_SEPARATOR . 'output',
                    $root . DIRECTORY_SEPARATOR . 'error.json',
                    'missing-success.pdf',
                    ['error' => 'missing success flag']
                )
            );
        } finally {
            $removeTree($root);
        }
    },
];
