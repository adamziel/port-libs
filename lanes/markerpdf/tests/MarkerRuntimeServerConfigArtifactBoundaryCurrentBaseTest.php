<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BenchmarkRunner;
use PortLibs\MarkerPDF\MarkerServerAdapter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-server-config-artifact-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf server config artifact folder.');
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
    'roundtrips marker server config as benchmark review artifact without exposing secrets' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $uploadRoot = $root . DIRECTORY_SEPARATOR . 'uploads';
            $artifactPath = $root . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'server-config.json';
            $adapter = new MarkerServerAdapter();
            $configBoundary = $adapter->serverConfigErrorBoundary([
                'host' => '0.0.0.0',
                'port' => '9123',
                'api_key' => 'wp-server-config-secret',
                'datalab_url' => 'https://api.example/marker',
                'upload_directory' => $uploadRoot,
                'ensure_upload_directory' => true,
            ]);
            $uploadPlan = $adapter->uploadRoutePlan(
                [
                    'max_pages' => 3,
                    'langs' => 'English,French',
                    'force_ocr' => true,
                    'paginate' => true,
                    'extract_images' => false,
                ],
                $uploadRoot,
                false
            );

            $serverResponse = $adapter->convertPdfFromUpload(
                [
                    'filename' => '../config artifact.pdf',
                    'content_type' => 'application/pdf',
                    'bytes' => '%PDF-1.4 server config source bytes',
                ],
                [
                    'max_pages' => 3,
                    'langs' => 'English,French',
                    'force_ocr' => true,
                    'paginate' => true,
                    'extract_images' => false,
                ],
                $uploadRoot,
                false,
                static fn (): string => 'unused-local-converter',
                static function (string $method, string $url, array $request): array {
                    return $method === 'POST'
                        ? ['request_check_url' => 'https://api.example/check/config-artifact']
                        : [
                            'status' => 'complete',
                            'success' => true,
                            'markdown' => 'Config artifact import.',
                            'images' => [],
                            'metadata' => ['route' => 'remote'],
                        ];
                },
                'wp-server-config-secret',
                'https://api.example/marker'
            );
            $uploadPath = $uploadRoot . DIRECTORY_SEPARATOR . 'config artifact.pdf';

            $artifact = (new BenchmarkRunner())->writeServerConfigArtifactJson(
                $artifactPath,
                $configBoundary,
                $uploadPlan,
                [
                    'phase' => 'server_config',
                    'method' => 'marker',
                    'document' => 'config artifact.pdf',
                    'benchmark_index' => 7,
                    'server_route' => 'remote',
                    'uploaded_filename' => 'config artifact.pdf',
                    'upload_removed' => !is_file($uploadPath),
                    'request_count' => 2,
                    'server_success' => $serverResponse['success'],
                    'server_response_status' => $serverResponse['status'],
                    'markdown_output_folder' => $root . DIRECTORY_SEPARATOR . 'markdown',
                    'report_output' => $root . DIRECTORY_SEPARATOR . 'overall.json',
                ]
            );

            $t->same($artifactPath, $artifact['path']);
            $t->same('server-config.json', $artifact['filename']);
            $t->same('json', $artifact['format']);
            $t->same('markerpdf.server_config_artifact.v1', $artifact['schema']);
            $t->same('remote', $artifact['status']);
            $t->same(true, $artifact['api_key_configured']);
            $t->same(false, $artifact['local']);
            $t->same(true, $artifact['upload_directory_created']);
            $t->same(true, $artifact['upload_removed']);
            $t->same(true, $artifact['review_only']);
            $t->same(hash_file('sha256', $artifactPath), $artifact['sha256']);

            $artifactJson = (string) file_get_contents($artifactPath);
            $t->true(!str_contains($artifactJson, 'wp-server-config-secret'));
            $t->true(!str_contains($artifactJson, 'server config source bytes'));
            $t->true(!str_contains($artifactJson, '%PDF-1.4'));

            $roundtrip = (new BenchmarkRunner())->readServerConfigArtifactJson($artifactPath);
            $payload = $roundtrip['payload'];
            $config = $payload['server_config'];
            $route = $payload['upload_route'];
            $context = $payload['context'];

            $t->same($artifactPath, $roundtrip['path']);
            $t->same('markerpdf.server_config_artifact.v1', $payload['schema']);
            $t->same('sddai/markerPDF marker_server.py + benchmarks/overall.py runtime config artifact', $payload['source']);
            $t->same('remote', $payload['status']);
            $t->same(true, $payload['success']);
            $t->same(false, $config['local']);
            $t->same(true, $config['api_key_configured']);
            $t->same(false, $config['api_key_value_stored']);
            $t->same('https://api.example/marker', $config['datalab_url']);
            $t->same(['app' => 'marker_server:app', 'host' => '0.0.0.0', 'port' => 9123], $config['uvicorn']);
            $t->same('created', $config['upload_directory_status']);
            $t->same(true, $config['upload_directory_created']);
            $t->same('remote', $route['selected_route']);
            $t->same(['file', 'max_pages', 'langs', 'force_ocr', 'paginate', 'extract_images'], $route['forwards_multipart_fields']);
            $t->same(3, $route['multipart_field_values']['max_pages']);
            $t->same('English,French', $route['multipart_field_values']['langs']);
            $t->same(true, $route['multipart_field_values']['force_ocr']);
            $t->same(true, $route['multipart_field_values']['paginate']);
            $t->same(false, $route['multipart_field_values']['extract_images']);
            $t->same(true, $route['cleanup']['removes_upload_after_success']);
            $t->same(true, $route['cleanup']['removes_upload_after_body_error']);
            $t->same('server_config', $context['phase']);
            $t->same('marker', $context['method']);
            $t->same('config artifact.pdf', $context['document']);
            $t->same('remote', $context['server_route']);
            $t->same(true, $context['upload_removed']);
            $t->same(2, $context['request_count']);
            $t->same(true, $context['server_success']);
            $t->same('complete', $context['server_response_status']);
            $t->same(true, $payload['default_upload_directory_created_before_requests']);
            $t->same(true, $payload['default_upload_temp_file_removed_after_conversion']);
            $t->same(true, $payload['excludes_api_key_value']);
            $t->same(true, $payload['excludes_uploaded_pdf_bytes']);
            $t->same(true, $roundtrip['roundtrip_preserves_config_boundary']);
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
    'records local config artifacts while preserving upload local option boundaries' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $uploadRoot = $root . DIRECTORY_SEPARATOR . 'planned-uploads';
            $artifactPath = $root . DIRECTORY_SEPARATOR . 'local-config.json';
            $adapter = new MarkerServerAdapter();
            $configBoundary = $adapter->serverConfigErrorBoundary([
                'upload_directory' => $uploadRoot,
            ]);
            $uploadPlan = $adapter->uploadRoutePlan(
                [
                    'max_pages' => 1,
                    'langs' => 'English',
                    'force_ocr' => false,
                    'paginate' => true,
                    'extract_images' => false,
                ],
                $uploadRoot,
                true
            );

            $artifact = (new BenchmarkRunner())->writeServerConfigArtifactJson(
                $artifactPath,
                $configBoundary,
                $uploadPlan,
                [
                    'phase' => 'server_config',
                    'method' => 'marker',
                    'document' => 'local-config.pdf',
                    'server_route' => 'local',
                ]
            );
            $roundtrip = (new BenchmarkRunner())->readServerConfigArtifactJson($artifactPath);
            $payload = $roundtrip['payload'];

            $t->same('local', $artifact['status']);
            $t->same(false, $artifact['api_key_configured']);
            $t->same(true, $artifact['local']);
            $t->same(false, $artifact['upload_directory_created']);
            $t->same(true, $payload['server_config']['local']);
            $t->same(false, $payload['server_config']['api_key_configured']);
            $t->same(false, $payload['server_config']['api_key_value_stored']);
            $t->same('planned', $payload['server_config']['upload_directory_status']);
            $t->same(false, is_dir($uploadRoot));
            $t->same('local', $payload['upload_route']['selected_route']);
            $t->same(false, $payload['upload_route']['local_route']['applies_direct_marker_option_guard']);
            $t->same(false, $payload['upload_route']['local_route']['paginate_forwarded_to_convert_single_pdf']);
            $t->same(false, $payload['upload_route']['local_route']['extract_images_forwarded_to_convert_single_pdf']);
            $t->same(['max_pages' => 1, 'langs' => 'English', 'ocr_all_pages' => false], $payload['upload_route']['local_route']['forwards_convert_single_pdf_options']);
            $t->same('Marker server config artifact recorded for marker/local-config.pdf', $payload['message_line']);
            $t->same(true, $roundtrip['roundtrip_preserves_config_boundary']);
        } finally {
            $removeTree($root);
        }
    },
    'rejects malformed server config artifact inputs and tampered roundtrips' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $runner = new BenchmarkRunner();
            $artifactPath = $root . DIRECTORY_SEPARATOR . 'server-config.json';
            $adapter = new MarkerServerAdapter();
            $validConfig = $adapter->serverConfigErrorBoundary();
            $validUpload = $adapter->uploadRoutePlan();

            $t->throws(
                InvalidArgumentException::class,
                static fn () => $runner->writeServerConfigArtifactJson($artifactPath, ['success' => false, 'error' => 'bad config'], $validUpload)
            );
            $t->throws(
                InvalidArgumentException::class,
                static fn () => $runner->writeServerConfigArtifactJson($artifactPath, $validConfig, ['endpoint' => '/other'])
            );

            file_put_contents($artifactPath, json_encode([
                'schema' => 'markerpdf.server_config_artifact.v1',
                'success' => true,
                'status' => 'remote',
                'server_config' => [
                    'local' => false,
                    'api_key_configured' => true,
                    'api_key_value_stored' => true,
                ],
                'upload_route' => ['endpoint' => '/marker/upload'],
                'review_only' => true,
            ], JSON_THROW_ON_ERROR));

            $t->throws(
                InvalidArgumentException::class,
                static fn () => $runner->readServerConfigArtifactJson($artifactPath)
            );
        } finally {
            $removeTree($root);
        }
    },
];
