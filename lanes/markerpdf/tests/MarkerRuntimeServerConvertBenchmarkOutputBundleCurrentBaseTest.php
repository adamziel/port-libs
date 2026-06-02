<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BenchmarkRunner;
use PortLibs\MarkerPDF\MarkerServerAdapter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-server-output-bundle-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf server output bundle folder.');
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
    'bundles successful marker server conversion output as benchmark artifacts' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $uploadRoot = $root . DIRECTORY_SEPARATOR . 'uploads';
            $outputRoot = $root . DIRECTORY_SEPARATOR . 'benchmark-output';

            $serverResponse = (new MarkerServerAdapter())->convertPdfFromUpload(
                [
                    'filename' => 'server-output-bundle.pdf',
                    'content_type' => 'application/pdf',
                    'bytes' => '%PDF-1.4 output bundle fixture',
                ],
                [
                    'max_pages' => 1,
                    'langs' => 'English',
                    'force_ocr' => false,
                ],
                $uploadRoot,
                true,
                static function (string $filepath, array $options): array {
                    return [
                        'markdown' => "Server bundle visible text.\n\n![Chart](../chart preview?.jpg)",
                        'images' => ['../chart preview?.jpg' => 'PNG-CHART-BYTES'],
                        'metadata' => [
                            'source' => basename($filepath),
                            'image' => '../chart preview?.jpg',
                            'options' => $options,
                        ],
                    ];
                }
            );

            $uploadPath = $uploadRoot . DIRECTORY_SEPARATOR . 'server-output-bundle.pdf';
            $t->same(true, $serverResponse['success']);
            $t->same(false, is_file($uploadPath));
            $t->same('UE5HLUNIQVJULUJZVEVT', $serverResponse['images']['../chart preview?.jpg']);

            $bundle = (new BenchmarkRunner())->writeServerBenchmarkOutputBundle(
                $outputRoot,
                '../server-output-bundle.pdf',
                $serverResponse,
                [
                    'phase' => 'server_upload',
                    'method' => 'marker',
                    'document' => 'server-output-bundle.pdf',
                    'benchmark_index' => '0',
                    'markdown_output_folder' => $outputRoot,
                    'report_output' => $root . DIRECTORY_SEPARATOR . 'overall.json',
                    'upload_removed' => !is_file($uploadPath),
                    'request_count' => 0,
                ]
            );

            $t->same('markerpdf.server_benchmark_output_bundle.v1', $bundle['schema']);
            $t->same('complete', $bundle['status']);
            $t->same(true, $bundle['success']);
            $t->same('server-output-bundle.pdf', $bundle['document']);
            $t->same('marker', $bundle['context']['method']);
            $t->same(0, $bundle['context']['benchmark_index']);
            $t->same(true, $bundle['context']['upload_removed']);
            $t->same(0, $bundle['context']['request_count']);
            $t->same(true, $bundle['benchmark_output_bundle_written']);
            $t->same(false, $bundle['success_report_written']);
            $t->same(false, $bundle['executes_fastapi']);
            $t->same(false, $bundle['executes_uvicorn']);
            $t->same(false, $bundle['executes_live_http']);
            $t->same(false, $bundle['executes_external_tools']);
            $t->same(false, $bundle['executes_python_or_models']);

            $output = $bundle['output_artifacts'];
            $markdownPath = $output['markdown_artifact']['path'];
            $metadataPath = $output['metadata_artifact']['path'];
            $imagePath = $output['image_artifacts'][0]['path'];
            $bundlePath = $bundle['bundle_artifact']['path'];

            $t->same('marker_output_runtime_preview_artifact_boundary', $output['source']);
            $t->same('server-output-bundle.md', $output['markdown_artifact']['filename']);
            $t->same('server-output-bundle_meta.json', $output['metadata_artifact']['filename']);
            $t->same('server-output-bundle_benchmark_bundle.json', $bundle['bundle_artifact']['filename']);
            $t->same(true, is_file($markdownPath));
            $t->same(true, is_file($metadataPath));
            $t->same(true, is_file($imagePath));
            $t->same(true, is_file($bundlePath));
            $t->same('PNG-CHART-BYTES', file_get_contents($imagePath));

            $markdown = (string) file_get_contents($markdownPath);
            $metadata = (string) file_get_contents($metadataPath);
            $payloadJson = (string) file_get_contents($bundlePath);
            $payload = json_decode($payloadJson, true, flags: JSON_THROW_ON_ERROR);

            $t->contains('Server bundle visible text.', $markdown);
            $t->contains('![Chart](chart_preview.png)', $markdown);
            $t->true(!str_contains($markdown, '../chart preview?.jpg'));
            $t->true(!str_contains($markdown, 'UE5HLUNIQVJULUJZVEVT'));
            $t->contains('"chart_preview.png"', $metadata);
            $t->true(!str_contains($metadata, '../chart preview?.jpg'));
            $t->true(!str_contains($metadata, 'PNG-CHART-BYTES'));
            $t->same(['../chart preview?.jpg' => 'chart_preview.png'], $output['image_name_map']);
            $t->same(false, $output['runtime_preview']['requested']);
            $t->same(null, $output['runtime_preview']['html']);
            $t->same(1, $bundle['server_response']['image_count']);
            $t->same(['image', 'options', 'source'], $bundle['server_response']['metadata_keys']);
            $t->same(strlen($serverResponse['markdown']), $bundle['server_response']['markdown_size']);
            $t->same(hash_file('sha256', $bundlePath), $bundle['bundle_artifact']['sha256']);
            $t->true($bundle['bundle_artifact']['size'] > 1000);
            $t->same('markerpdf.server_benchmark_output_bundle.v1', $payload['schema']);
            $t->same('server-output-bundle_benchmark_bundle.json', $payload['artifact']['filename']);
            $t->same('chart_preview.png', $payload['output_artifacts']['image_artifacts'][0]['filename']);
            $t->true(!str_contains($payloadJson, 'UE5HLUNIQVJULUJZVEVT'));
            $t->true(!str_contains($payloadJson, 'PNG-CHART-BYTES'));
            $t->true(!str_contains($payloadJson, '../chart preview?.jpg'));

            $roundtrip = (new BenchmarkRunner())->readServerBenchmarkOutputBundleJson($bundlePath);
            $t->same($bundlePath, $roundtrip['path']);
            $t->same($bundle['bundle_artifact']['sha256'], $roundtrip['sha256']);
            $t->same(true, $roundtrip['roundtrip_preserves_output_bundle']);
            $t->same('server-output-bundle.pdf', $roundtrip['payload']['document']);
            $t->same($markdownPath, $roundtrip['payload']['output_artifacts']['markdown_artifact']['path']);
        } finally {
            $removeTree($root);
        }
    },
    'rejects failed server responses and malformed output bundle artifacts' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $runner = new BenchmarkRunner();
            $outputRoot = $root . DIRECTORY_SEPARATOR . 'output';
            $bundlePath = $root . DIRECTORY_SEPARATOR . 'wrong-bundle.json';

            $t->throws(
                InvalidArgumentException::class,
                static fn () => $runner->writeServerBenchmarkOutputBundle(
                    $outputRoot,
                    'failed.pdf',
                    ['success' => false, 'error' => 'server failed']
                )
            );
            $t->throws(
                InvalidArgumentException::class,
                static fn () => $runner->writeServerBenchmarkOutputBundle(
                    $outputRoot,
                    'missing-markdown.pdf',
                    ['success' => true, 'images' => [], 'metadata' => []]
                )
            );
            $t->throws(
                InvalidArgumentException::class,
                static fn () => $runner->writeServerBenchmarkOutputBundle(
                    $outputRoot,
                    'bad-image.pdf',
                    ['success' => true, 'markdown' => 'ok', 'images' => ['bad.png' => 'not base64 ****'], 'metadata' => []]
                )
            );

            file_put_contents($bundlePath, json_encode([
                'schema' => 'markerpdf.other.v1',
                'success' => true,
                'status' => 'complete',
                'review_only' => true,
            ], JSON_THROW_ON_ERROR));

            $t->throws(
                InvalidArgumentException::class,
                static fn () => $runner->readServerBenchmarkOutputBundleJson($bundlePath)
            );
        } finally {
            $removeTree($root);
        }
    },
];
