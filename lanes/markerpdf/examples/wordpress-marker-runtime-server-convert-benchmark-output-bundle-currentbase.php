<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BenchmarkRunner;
use PortLibs\MarkerPDF\MarkerServerAdapter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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

$root = sys_get_temp_dir() . '/markerpdf-server-output-bundle-smoke-' . bin2hex(random_bytes(4));
$uploadRoot = $root . DIRECTORY_SEPARATOR . 'uploads';
$outputRoot = $root . DIRECTORY_SEPARATOR . 'benchmark-output';

try {
    $serverResponse = (new MarkerServerAdapter())->convertPdfFromUpload(
        [
            'filename' => 'wp-server-output-bundle.pdf',
            'content_type' => 'application/pdf',
            'bytes' => '%PDF-1.4 WordPress server output bundle',
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
                'markdown' => "<!-- wp:paragraph -->\n<p>Server bundle import ready.</p>\n<!-- /wp:paragraph -->\n\n![Preview](../preview image?.jpg)",
                'images' => ['../preview image?.jpg' => 'PNG-WP-PREVIEW'],
                'metadata' => [
                    'source' => basename($filepath),
                    'image' => '../preview image?.jpg',
                    'options' => $options,
                ],
            ];
        }
    );

    if (($serverResponse['success'] ?? null) !== true) {
        throw new RuntimeException('Expected successful marker server conversion response.');
    }

    $uploadPath = $uploadRoot . DIRECTORY_SEPARATOR . 'wp-server-output-bundle.pdf';
    $bundle = (new BenchmarkRunner())->writeServerBenchmarkOutputBundle(
        $outputRoot,
        'wp-server-output-bundle.pdf',
        $serverResponse,
        [
            'phase' => 'server_upload',
            'method' => 'marker',
            'document' => 'wp-server-output-bundle.pdf',
            'benchmark_index' => 0,
            'markdown_output_folder' => $outputRoot,
            'report_output' => $root . DIRECTORY_SEPARATOR . 'overall.json',
            'upload_removed' => !is_file($uploadPath),
            'request_count' => 0,
        ]
    );
    $roundtrip = (new BenchmarkRunner())->readServerBenchmarkOutputBundleJson($bundle['bundle_artifact']['path']);

    $markdown = (string) file_get_contents($bundle['output_artifacts']['markdown_artifact']['path']);
    if (!str_contains($markdown, '![Preview](preview_image.png)')) {
        throw new RuntimeException('Expected output bundle to rewrite unsafe image target.');
    }
    if (str_contains($markdown, 'PNG-WP-PREVIEW') || str_contains($markdown, 'UE5HLVdQLVBSRVZJRVc=')) {
        throw new RuntimeException('Expected output bundle markdown to exclude raw image payloads.');
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-server-convert-benchmark-output-bundle-currentbase',
        'purpose' => 'Bundle successful marker_server.py conversion responses into benchmark/output markdown, metadata, image, and manifest artifacts for a WordPress import gate without Uvicorn, FastAPI, live HTTP, Python, models, Nougat, or external PDF tools.',
        'source' => $roundtrip['payload']['source'],
        'server_success' => $serverResponse['success'],
        'document' => $roundtrip['payload']['document'],
        'bundle_schema' => $bundle['schema'],
        'bundle_sha256_matches_readback' => $bundle['bundle_artifact']['sha256'] === $roundtrip['sha256'],
        'roundtrip_preserves_output_bundle' => $roundtrip['roundtrip_preserves_output_bundle'],
        'markdown_filename' => $bundle['output_artifacts']['markdown_artifact']['filename'],
        'metadata_filename' => $bundle['output_artifacts']['metadata_artifact']['filename'],
        'image_filename' => $bundle['output_artifacts']['image_artifacts'][0]['filename'],
        'image_payload_excluded_from_markdown' => !str_contains($markdown, 'PNG-WP-PREVIEW'),
        'upload_removed' => $bundle['context']['upload_removed'],
        'success_report_written' => $bundle['success_report_written'],
        'executes_fastapi' => $bundle['executes_fastapi'],
        'executes_uvicorn' => $bundle['executes_uvicorn'],
        'executes_live_http' => $bundle['executes_live_http'],
        'executes_external_tools' => $bundle['executes_external_tools'],
        'executes_python_or_models' => $bundle['executes_python_or_models'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($root);
}
