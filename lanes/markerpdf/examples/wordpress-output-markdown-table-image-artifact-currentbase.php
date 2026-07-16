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

$outputRoot = sys_get_temp_dir() . '/markerpdf-wordpress-table-image-artifact';
$removeTree($outputRoot);

$cover = '../uploads/table-cover?.jpeg';
$detail = '../uploads/table-detail.jpeg';
$markdown = <<<'MD'
<!-- wp:paragraph -->
<p>Marker saved a recognized table whose cells reference extracted image crops.</p>
<!-- /wp:paragraph -->

| Asset | Preview | Import state |
| --- | --- | --- |
| Cover crop | ![Cover crop](../uploads/table-cover?.jpeg "hero table crop") | saved |
| Detail crop | ![Detail crop](../uploads/table-detail.jpeg) | saved |
| Manual review | ![Missing table crop](missing-table-crop.png "not returned") | missing |
MD;

$manifest = (new OutputWriter())->saveMarkdownArtifactBoundary(
    $outputRoot,
    'wordpress-table-image-artifact.pdf',
    $markdown,
    [
        $cover => 'PNG-COVER',
        $detail => 'PNG-DETAIL',
        '7_image_0.png' => 'PNG-REVIEW-ONLY',
    ],
    [
        'scenario' => 'wordpress-output-markdown-table-image-artifact-currentbase',
        'images' => [$cover, $detail, '7_image_0.png'],
    ]
);

$artifact = $manifest['markdown_table_image_artifact'];
$markdownOut = (string) file_get_contents($manifest['markdown_artifact']['path']);

if (($artifact['table_count'] ?? 0) !== 1) {
    throw new RuntimeException('Expected one Markdown pipe table to be reviewed.');
}
if (($artifact['embedded_table_image_reference_count'] ?? 0) !== 2) {
    throw new RuntimeException('Expected two persisted table image references.');
}
if (($artifact['missing_table_image_targets'] ?? []) !== ['missing-table-crop.png']) {
    throw new RuntimeException('Expected missing table crop to remain reviewable.');
}
if (($artifact['unreferenced_table_image_artifacts'] ?? []) !== ['7_image_0.png']) {
    throw new RuntimeException('Expected non-table image artifact to remain review-only.');
}
if (!str_contains($markdownOut, '![Cover crop](table-cover.png "hero table crop")')) {
    throw new RuntimeException('Expected table-cell Markdown image target to be rewritten to the persisted PNG.');
}
if (($artifact['executes_streamlit'] ?? true) || ($artifact['executes_pdfium'] ?? true) || ($artifact['executes_python_or_models'] ?? true)) {
    throw new RuntimeException('Markdown table image artifact review must not execute upstream runtimes.');
}

echo json_encode([
    'scenario' => 'wordpress-output-markdown-table-image-artifact-currentbase',
    'source' => $artifact['source'],
    'table_count' => $artifact['table_count'],
    'table_row_count' => $artifact['table_row_count'],
    'table_image_reference_count' => $artifact['table_image_reference_count'],
    'embedded_table_image_reference_count' => $artifact['embedded_table_image_reference_count'],
    'missing_table_image_reference_count' => $artifact['missing_table_image_reference_count'],
    'expected_runtime_preview_table_data_uri_count' => $artifact['expected_runtime_preview_table_data_uri_count'],
    'missing_table_image_targets' => $artifact['missing_table_image_targets'],
    'unreferenced_table_image_artifacts' => $artifact['unreferenced_table_image_artifacts'],
    'cover_table_reference_rewritten' => str_contains($markdownOut, '![Cover crop](table-cover.png "hero table crop")'),
    'detail_table_reference_rewritten' => str_contains($markdownOut, '![Detail crop](table-detail.png)'),
    'runtime_preview_only' => $manifest['runtime_preview']['runtime_only'],
    'executes_streamlit' => $artifact['executes_streamlit'],
    'executes_pdfium' => $artifact['executes_pdfium'],
    'executes_python_or_models' => $artifact['executes_python_or_models'],
    'executes_external_pdf_tools' => $artifact['executes_external_pdf_tools'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
