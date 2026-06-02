<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\OutputWriter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-output-table-image-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf output table-image test folder.');
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
    'reports persisted and missing image artifacts inside markdown table cells' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $cover = '../media/cover?.jpeg';
            $detail = '../media/detail.jpeg';
            $markdown = <<<'MD'
Paragraph before the table.

| Asset | Preview | Status |
| --- | --- | --- |
| Cover crop | ![Cover crop](../media/cover?.jpeg "table cover") | ready |
| Detail crop | ![Detail crop](../media/detail.jpeg) ![Detail crop duplicate](../media/detail.jpeg) | repeated |
| Missing crop | ![Missing table crop](missing-table.png "manual crop") | review |

Paragraph after the table.
MD;

            $manifest = (new OutputWriter())->saveMarkdownArtifactBoundary(
                $root,
                'wordpress-table-images.pdf',
                $markdown,
                [
                    $cover => 'PNG-COVER',
                    $detail => 'PNG-DETAIL',
                    '4_image_0.png' => 'PNG-UNREFERENCED',
                ],
                [
                    'scenario' => 'wordpress-output-markdown-table-image-artifact-currentbase',
                    'images' => [$cover, $detail, '4_image_0.png'],
                    'table_image_cells' => [
                        ['label' => 'Cover crop', 'image' => $cover],
                        ['label' => 'Detail crop', 'image' => $detail],
                    ],
                ]
            );

            $subfolder = $root . DIRECTORY_SEPARATOR . 'wordpress-table-images';
            $markdownOut = (string) file_get_contents($subfolder . DIRECTORY_SEPARATOR . 'wordpress-table-images.md');
            $metadataOut = (string) file_get_contents($subfolder . DIRECTORY_SEPARATOR . 'wordpress-table-images_meta.json');

            $t->contains('| Cover crop | ![Cover crop](cover.png "table cover") | ready |', $markdownOut);
            $t->contains('| Detail crop | ![Detail crop](detail.png) ![Detail crop duplicate](detail.png) | repeated |', $markdownOut);
            $t->contains('| Missing crop | ![Missing table crop](missing-table.png "manual crop") | review |', $markdownOut);
            $t->true(!str_contains($markdownOut, $cover));
            $t->true(!str_contains($markdownOut, $detail));
            $t->true(!str_contains($metadataOut, $cover));
            $t->true(!str_contains($metadataOut, $detail));
            $t->contains('"cover.png"', $metadataOut);
            $t->contains('"detail.png"', $metadataOut);
            $t->same('PNG-COVER', file_get_contents($subfolder . DIRECTORY_SEPARATOR . 'cover.png'));
            $t->same('PNG-DETAIL', file_get_contents($subfolder . DIRECTORY_SEPARATOR . 'detail.png'));
            $t->same('PNG-UNREFERENCED', file_get_contents($subfolder . DIRECTORY_SEPARATOR . '4_image_0.png'));

            $artifact = $manifest['markdown_table_image_artifact'];
            $t->same('marker_output_markdown_table_image_artifact', $artifact['source']);
            $t->same(
                'marker.output.save_markdown + tabled.formats.markdown.markdown_format + marker_app.markdown_insert_images',
                $artifact['upstream_boundary']
            );
            $t->same(1, $artifact['table_count']);
            $t->same(4, $artifact['table_row_count']);
            $t->same(3, $artifact['table_data_row_count']);
            $t->same(4, $artifact['table_image_reference_count']);
            $t->same(3, $artifact['embedded_table_image_reference_count']);
            $t->same(1, $artifact['missing_table_image_reference_count']);
            $t->same(false, $artifact['table_preview_complete']);
            $t->same(true, $artifact['preview_html_requested']);
            $t->same(3, $artifact['expected_runtime_preview_table_data_uri_count']);
            $t->same([
                'cover.png' => 1,
                'detail.png' => 2,
                'missing-table.png' => 1,
            ], $artifact['target_reference_counts']);
            $t->same([
                'cover.png' => 1,
                'detail.png' => 2,
            ], $artifact['embedded_reference_counts']);
            $t->same(['missing-table.png' => 1], $artifact['missing_reference_counts']);
            $t->same(['cover.png', 'detail.png', 'missing-table.png'], $artifact['unique_table_image_targets']);
            $t->same(['missing-table.png'], $artifact['missing_table_image_targets']);
            $t->same(['4_image_0.png'], $artifact['unreferenced_table_image_artifacts']);

            $table = $artifact['tables'][0];
            $t->same(['Asset', 'Preview', 'Status'], $table['header']);
            $t->same(3, $table['column_count']);
            $t->same(4, $table['image_reference_count']);
            $t->same(3, $table['embedded_image_reference_count']);
            $t->same(1, $table['missing_image_reference_count']);

            $references = $artifact['references'];
            $t->same(4, count($references));
            $t->same(1, $references[0]['row_index']);
            $t->same(0, $references[0]['data_row_index']);
            $t->same('data', $references[0]['row_type']);
            $t->same(1, $references[0]['column_index']);
            $t->same('Preview', $references[0]['column_heading']);
            $t->same('Cover crop', $references[0]['alt']);
            $t->same('cover.png', $references[0]['target']);
            $t->same('"table cover"', $references[0]['title']);
            $t->same(true, $references[0]['embedded_as_persisted_image']);
            $t->same(false, $references[0]['missing_persisted_image']);
            $t->same($cover, $references[0]['source_filename']);
            $t->same(hash('sha256', 'PNG-COVER'), $references[0]['artifact_sha256']);
            $t->same('missing-table.png', $references[3]['target']);
            $t->same('"manual crop"', $references[3]['title']);
            $t->same(false, $references[3]['embedded_as_persisted_image']);
            $t->same(true, $references[3]['missing_persisted_image']);
            $t->same(null, $references[3]['artifact_filename']);

            $preview = $manifest['runtime_preview'];
            $t->same(3, $preview['image_data_uri_count']);
            $t->contains('data:image/png;base64,UE5HLUNPVkVS', (string) $preview['html']);
            $t->contains('data:image/png;base64,UE5HLURFVEFJTA==', (string) $preview['html']);
            $t->contains('![Missing table crop](missing-table.png "manual crop")', (string) $preview['html']);
            $t->same(false, $artifact['executes_streamlit']);
            $t->same(false, $artifact['executes_pdfium']);
            $t->same(false, $artifact['executes_python_or_models']);
            $t->same(false, $artifact['executes_external_pdf_tools']);
        } finally {
            $removeTree($root);
        }
    },
    'keeps markdown table image artifact accounting when runtime preview html is disabled' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $manifest = (new OutputWriter())->saveMarkdownArtifactBoundary(
                $root,
                'table-no-preview.pdf',
                "| Asset | Preview |\n| --- | --- |\n| Known | ![Known](0_image_0.png) |",
                ['0_image_0.png' => 'PNG'],
                ['scenario' => 'table-no-preview'],
                includeRuntimePreviewHtml: false
            );

            $artifact = $manifest['markdown_table_image_artifact'];
            $t->same(1, $artifact['table_count']);
            $t->same(1, $artifact['table_image_reference_count']);
            $t->same(1, $artifact['embedded_table_image_reference_count']);
            $t->same(0, $artifact['missing_table_image_reference_count']);
            $t->same(true, $artifact['table_preview_complete']);
            $t->same(false, $artifact['preview_html_requested']);
            $t->same(null, $artifact['expected_runtime_preview_table_data_uri_count']);
            $t->same(null, $manifest['runtime_preview']['html']);
            $t->same(0, $manifest['runtime_preview']['image_data_uri_count']);
            $t->same(['0_image_0.png' => 1], $artifact['target_reference_counts']);
            $t->same([], $artifact['unreferenced_table_image_artifacts']);
        } finally {
            $removeTree($root);
        }
    },
];
