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

$outputRoot = sys_get_temp_dir() . '/markerpdf-wordpress-output-image-bundle';
$removeTree($outputRoot);

$pngChunk = static function (string $type, string $data): string {
    return pack('N', strlen($data)) . $type . $data . pack('N', (int) hexdec(hash('crc32b', $type . $data)));
};
$validPng = "\x89PNG\r\n\x1a\n"
    . $pngChunk('IHDR', pack('NNC5', 1, 1, 8, 6, 0, 0, 0))
    . $pngChunk('IDAT', gzcompress("\x00\x00\x00\x00\x00"))
    . $pngChunk('IEND', '');

$cover = '../wp-output/cover.jpeg';
$detail = '../wp-output/cover?.jpeg';
$markdown = <<<'MD'
<!-- wp:paragraph -->
<p>Marker saved a Markdown artifact with extracted image references.</p>
<!-- /wp:paragraph -->

![Cover artifact](../wp-output/cover.jpeg "page crop")
![Detail artifact](../wp-output/cover?.jpeg "detail crop")
![Missing crop](missing-crop.png "not returned")
MD;

$manifest = (new OutputWriter())->saveMarkdownArtifactBoundary(
    $outputRoot,
    'wordpress-output-image-bundle.pdf',
    $markdown,
    [
        $cover => $validPng,
        $detail => $validPng,
        '5_image_0.png' => 'BROKEN-REVIEW-ONLY',
    ],
    [
        'scenario' => 'wordpress-output-artifact-preview-markdown-image-bundle-currentbase',
        'images' => [$cover, $detail, '5_image_0.png'],
    ]
);

$bundle = $manifest['markdown_image_bundle'];
$markdownOut = (string) file_get_contents($manifest['markdown_artifact']['path']);

if (!str_contains($markdownOut, '![Cover artifact](cover.png "page crop")')) {
    throw new RuntimeException('Expected title-bearing cover Markdown reference to be rewritten.');
}
if (($bundle['preview_data_uri_count_matches_embedded_references'] ?? false) !== true) {
    throw new RuntimeException('Expected runtime preview data URI count to match embedded Markdown references.');
}
if (($bundle['missing_markdown_image_targets'] ?? []) !== ['missing-crop.png']) {
    throw new RuntimeException('Expected missing Markdown image reference to remain reviewable.');
}
if (($bundle['unreferenced_image_artifacts'] ?? []) !== ['5_image_0.png']) {
    throw new RuntimeException('Expected unreferenced saved image artifact to remain review-only.');
}
$quality = $bundle['image_quality'] ?? [];
if (($quality['wordpress_media_importable_count'] ?? null) !== 2) {
    throw new RuntimeException('Expected referenced PNG artifacts to be importable by WordPress media review.');
}
if (($quality['unimportable_image_artifacts'] ?? []) !== ['5_image_0.png']) {
    throw new RuntimeException('Expected malformed review-only PNG artifact to be flagged.');
}
if (($bundle['executes_streamlit'] ?? true) || ($bundle['executes_pdfium'] ?? true) || ($bundle['executes_python_or_models'] ?? true)) {
    throw new RuntimeException('Output image bundle review must not execute upstream runtimes.');
}

echo json_encode([
    'scenario' => 'wordpress-output-artifact-preview-markdown-image-bundle-currentbase',
    'source' => $bundle['source'],
    'markdown_reference_count' => $bundle['markdown_reference_count'],
    'image_artifact_count' => $bundle['image_artifact_count'],
    'embedded_reference_count' => $bundle['embedded_reference_count'],
    'missing_reference_count' => $bundle['missing_reference_count'],
    'unreferenced_artifact_count' => $bundle['unreferenced_artifact_count'],
    'preview_data_uri_count' => $bundle['preview_data_uri_count'],
    'preview_data_uri_count_matches_embedded_references' => $bundle['preview_data_uri_count_matches_embedded_references'],
    'missing_markdown_image_targets' => $bundle['missing_markdown_image_targets'],
    'unreferenced_image_artifacts' => $bundle['unreferenced_image_artifacts'],
    'wordpress_media_importable_count' => $quality['wordpress_media_importable_count'],
    'wordpress_media_unimportable_count' => $quality['wordpress_media_unimportable_count'],
    'unimportable_image_artifacts' => $quality['unimportable_image_artifacts'],
    'quality_warning_counts' => $quality['quality_warning_counts'],
    'cover_title_reference_rewritten' => str_contains($markdownOut, '![Cover artifact](cover.png "page crop")'),
    'detail_title_reference_rewritten' => str_contains($markdownOut, '![Detail artifact](cover_2.png "detail crop")'),
    'runtime_preview_only' => $manifest['runtime_preview']['runtime_only'],
    'executes_streamlit' => $bundle['executes_streamlit'],
    'executes_pdfium' => $bundle['executes_pdfium'],
    'executes_python_or_models' => $bundle['executes_python_or_models'],
    'executes_external_pdf_tools' => $bundle['executes_external_pdf_tools'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
