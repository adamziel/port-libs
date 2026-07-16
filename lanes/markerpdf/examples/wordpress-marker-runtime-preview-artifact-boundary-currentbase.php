<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\OutputWriter;

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

$outputRoot = sys_get_temp_dir() . '/markerpdf-wordpress-runtime-preview-artifact';
$removeTree($outputRoot);

$unsafeImageName = '../wp preview?.jpeg';
$markdown = <<<'MD'
<!-- wp:paragraph -->
<p>Marker runtime finished conversion and handed off review artifacts.</p>
<!-- /wp:paragraph -->

![WordPress preview](../wp preview?.jpeg)
![Missing upstream crop](missing-crop.png)

<!-- wp:image -->
<figure class="wp-block-image"><img src="../wp preview?.jpeg" alt="../wp preview?.jpeg"/></figure>
<!-- /wp:image -->
MD;

$manifest = (new OutputWriter())->saveMarkdownArtifactBoundary(
    $outputRoot,
    'wordpress-runtime-preview.pdf',
    $markdown,
    [$unsafeImageName => 'PNG-WP-PREVIEW'],
    [
        'scenario' => 'wordpress-marker-runtime-preview-artifact-boundary-currentbase',
        'source' => 'convert.py tuple plus marker.output save_markdown',
        'images' => [$unsafeImageName],
    ]
);

$preview = $manifest['runtime_preview'];
$markdownOut = (string) file_get_contents($manifest['markdown_artifact']['path']);
$metadataOut = (string) file_get_contents($manifest['metadata_artifact']['path']);
$imagePath = $manifest['image_artifacts'][0]['path'] ?? '';

if (($preview['image_data_uri_count'] ?? 0) !== 1) {
    throw new RuntimeException('Expected exactly one runtime preview image data URI.');
}
if (!str_contains((string) $preview['html'], '![Missing upstream crop](missing-crop.png)')) {
    throw new RuntimeException('Expected missing upstream preview crop link to remain reviewable.');
}
if (str_contains($markdownOut, 'data:image/png;base64,') || str_contains($metadataOut, 'PNG-WP-PREVIEW')) {
    throw new RuntimeException('Runtime preview payload leaked into persisted Markdown or metadata.');
}
if (($manifest['executes_streamlit'] ?? true) || ($manifest['executes_pdfium'] ?? true) || ($manifest['executes_python_or_models'] ?? true)) {
    throw new RuntimeException('Runtime preview artifact boundary must not execute upstream runtimes.');
}

echo json_encode([
    'scenario' => 'wordpress-marker-runtime-preview-artifact-boundary-currentbase',
    'source' => $manifest['source'],
    'subfolder' => $manifest['subfolder'],
    'markdown_exists' => $manifest['markdown_artifact']['exists'],
    'metadata_exists' => $manifest['metadata_artifact']['exists'],
    'sanitized_image' => $manifest['image_artifacts'][0]['filename'] ?? null,
    'sanitized_image_exists' => is_string($imagePath) && is_file($imagePath),
    'markdown_rewritten_to_sanitized_image' => str_contains($markdownOut, 'wp_preview.png'),
    'metadata_rewritten_to_sanitized_image' => str_contains($metadataOut, 'wp_preview.png'),
    'runtime_preview_only' => $preview['runtime_only'],
    'runtime_preview_persisted' => $preview['persisted_to_output_folder'],
    'runtime_preview_data_uri_count' => $preview['image_data_uri_count'],
    'missing_preview_link_preserved' => in_array('missing-crop.png', $preview['unembedded_markdown_image_targets'], true),
    'markdown_keeps_file_references' => $preview['markdown_keeps_file_references'],
    'executes_streamlit' => $manifest['executes_streamlit'],
    'executes_pdfium' => $manifest['executes_pdfium'],
    'executes_python_or_models' => $manifest['executes_python_or_models'],
    'executes_external_pdf_tools' => $manifest['executes_external_pdf_tools'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
