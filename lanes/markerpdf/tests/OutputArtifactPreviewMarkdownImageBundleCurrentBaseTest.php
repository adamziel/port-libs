<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\OutputWriter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-output-image-bundle-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf output image bundle test folder.');
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
    'bundles saved markdown image artifacts with optional-title runtime preview targets' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $writer = new OutputWriter();
            $cover = '../figures/chapter-cover.jpeg';
            $coverCollision = '../figures/chapter-cover?.jpeg';
            $coverSpace = '../figures/chapter cover?.jpeg';
            $unused = '2_image_0.png';
            $markdown = <<<'MD'
<!-- wp:paragraph -->
<p>Output bundle review.</p>
<!-- /wp:paragraph -->

![Chapter cover](../figures/chapter-cover.jpeg "source crop")
![Chapter cover duplicate](../figures/chapter-cover?.jpeg 'collision crop')
![../figures/chapter cover?.jpeg](../figures/chapter cover?.jpeg)
![Missing preview](missing-preview.png "missing crop")

<!-- wp:image -->
<figure class="wp-block-image"><img src="../figures/chapter-cover?.jpeg" alt="../figures/chapter-cover?.jpeg"/></figure>
<!-- /wp:image -->
MD;

            $manifest = $writer->saveMarkdownArtifactBoundary(
                $root,
                'wp-preview-bundle.pdf',
                $markdown,
                [
                    $cover => 'PNG-COVER',
                    $coverCollision => 'PNG-COLLISION',
                    $coverSpace => 'PNG-SPACE',
                    $unused => 'PNG-UNUSED',
                ],
                [
                    'scenario' => 'wordpress-output-artifact-preview-markdown-image-bundle-currentbase',
                    'images' => [$cover, $coverCollision, $coverSpace, $unused],
                    'image_map' => [
                        $cover => ['role' => 'hero', 'filename' => $cover],
                        $coverCollision => ['role' => 'detail', 'filename' => $coverCollision],
                        $coverSpace => ['role' => 'legacy-alt', 'filename' => $coverSpace],
                    ],
                ]
            );

            $subfolder = $root . DIRECTORY_SEPARATOR . 'wp-preview-bundle';
            $markdownOut = (string) file_get_contents($subfolder . DIRECTORY_SEPARATOR . 'wp-preview-bundle.md');
            $metadataOut = (string) file_get_contents($subfolder . DIRECTORY_SEPARATOR . 'wp-preview-bundle_meta.json');

            $t->contains('![Chapter cover](chapter-cover.png "source crop")', $markdownOut);
            $t->contains("![Chapter cover duplicate](chapter-cover_2.png 'collision crop')", $markdownOut);
            $t->contains('![chapter_cover.png](chapter_cover.png)', $markdownOut);
            $t->contains('![Missing preview](missing-preview.png "missing crop")', $markdownOut);
            $t->contains('<img src="chapter-cover_2.png" alt="chapter-cover_2.png"', $markdownOut);
            $t->true(!str_contains($markdownOut, $cover));
            $t->true(!str_contains($markdownOut, $coverCollision));
            $t->true(!str_contains($markdownOut, $coverSpace));
            $t->true(!str_contains($markdownOut, 'PNG-COVER'));
            $t->contains('"chapter-cover.png"', $metadataOut);
            $t->contains('"chapter-cover_2.png"', $metadataOut);
            $t->contains('"chapter_cover.png"', $metadataOut);
            $t->true(!str_contains($metadataOut, $cover));
            $t->true(!str_contains($metadataOut, $coverCollision));
            $t->true(!str_contains($metadataOut, $coverSpace));
            $t->same('PNG-COVER', file_get_contents($subfolder . DIRECTORY_SEPARATOR . 'chapter-cover.png'));
            $t->same('PNG-COLLISION', file_get_contents($subfolder . DIRECTORY_SEPARATOR . 'chapter-cover_2.png'));
            $t->same('PNG-SPACE', file_get_contents($subfolder . DIRECTORY_SEPARATOR . 'chapter_cover.png'));
            $t->same('PNG-UNUSED', file_get_contents($subfolder . DIRECTORY_SEPARATOR . '2_image_0.png'));
            $t->same([
                $cover => 'chapter-cover.png',
                $coverCollision => 'chapter-cover_2.png',
                $coverSpace => 'chapter_cover.png',
            ], $manifest['image_name_map']);

            $preview = $manifest['runtime_preview'];
            $t->same(['chapter-cover.png', 'chapter-cover_2.png', 'chapter_cover.png', 'missing-preview.png'], $preview['markdown_image_targets']);
            $t->same(['chapter-cover.png', 'chapter-cover_2.png', 'chapter_cover.png'], $preview['embedded_image_targets']);
            $t->same(['missing-preview.png'], $preview['unembedded_markdown_image_targets']);
            $t->same(3, $preview['image_data_uri_count']);
            $t->same(true, $preview['markdown_keeps_file_references']);
            $t->contains('data:image/png;base64,UE5HLUNPVkVS', (string) $preview['html']);
            $t->contains('data:image/png;base64,UE5HLUNPTExJU0lPTg==', (string) $preview['html']);
            $t->contains('data:image/png;base64,UE5HLVNQQUNF', (string) $preview['html']);
            $t->contains('alt="Chapter cover"', (string) $preview['html']);
            $t->contains('alt="Chapter cover duplicate"', (string) $preview['html']);
            $t->contains('alt="chapter_cover.png"', (string) $preview['html']);
            $t->contains('![Missing preview](missing-preview.png "missing crop")', (string) $preview['html']);
            $t->true(!str_contains((string) $preview['html'], $cover));
            $t->true(!str_contains((string) $preview['html'], $coverCollision));
            $t->true(!str_contains((string) $preview['html'], $coverSpace));

            $bundle = $manifest['markdown_image_bundle'];
            $t->same('marker_output_artifact_preview_markdown_image_bundle', $bundle['source']);
            $t->same('marker.output.save_markdown + marker.images.save.images_to_dict + marker_app.markdown_insert_images', $bundle['upstream_boundary']);
            $t->same(4, $bundle['image_artifact_count']);
            $t->same(4, $bundle['markdown_reference_count']);
            $t->same(3, $bundle['embedded_reference_count']);
            $t->same(1, $bundle['missing_reference_count']);
            $t->same(1, $bundle['unreferenced_artifact_count']);
            $t->same(false, $bundle['markdown_preview_complete']);
            $t->same(true, $bundle['preview_html_requested']);
            $t->same(3, $bundle['preview_data_uri_count']);
            $t->same(true, $bundle['preview_data_uri_count_matches_embedded_references']);
            $t->same(['missing-preview.png'], $bundle['missing_markdown_image_targets']);
            $t->same(['2_image_0.png'], $bundle['unreferenced_image_artifacts']);
            $t->same([
                'chapter-cover.png' => 1,
                'chapter-cover_2.png' => 1,
                'chapter_cover.png' => 1,
                'missing-preview.png' => 1,
            ], $bundle['target_reference_counts']);
            $t->same([
                'chapter-cover.png' => 1,
                'chapter-cover_2.png' => 1,
                'chapter_cover.png' => 1,
            ], $bundle['embedded_reference_counts']);
            $t->same(['missing-preview.png' => 1], $bundle['missing_reference_counts']);
            $t->same(4, count($bundle['image_artifacts']));
            $t->same(3, count($bundle['referenced_image_artifacts']));

            $rows = [];
            foreach ($bundle['image_artifacts'] as $row) {
                $rows[$row['filename']] = $row;
            }

            $t->same($cover, $rows['chapter-cover.png']['source_filename']);
            $t->same(true, $rows['chapter-cover.png']['source_filename_rewritten']);
            $t->same(true, $rows['chapter-cover.png']['referenced_in_markdown']);
            $t->same(1, $rows['chapter-cover.png']['markdown_reference_count']);
            $t->same(1, $rows['chapter-cover.png']['runtime_preview_embedded_count']);
            $t->same(hash('sha256', 'PNG-COVER'), $rows['chapter-cover.png']['sha256']);
            $t->same(9, $rows['chapter-cover.png']['size']);
            $t->same(true, $rows['chapter-cover.png']['persisted_to_output_folder']);
            $t->same('image/png', $rows['chapter-cover.png']['mime_type']);
            $t->same(true, $rows['chapter-cover.png']['runtime_preview_embeddable']);
            $t->same(false, $rows['2_image_0.png']['source_filename_rewritten']);
            $t->same(false, $rows['2_image_0.png']['referenced_in_markdown']);
            $t->same(0, $rows['2_image_0.png']['markdown_reference_count']);
            $t->same(0, $rows['2_image_0.png']['runtime_preview_embedded_count']);
            $t->same(false, $bundle['executes_streamlit']);
            $t->same(false, $bundle['executes_pdfium']);
            $t->same(false, $bundle['executes_python_or_models']);
            $t->same(false, $bundle['executes_external_pdf_tools']);
        } finally {
            $removeTree($root);
        }
    },
    'reports bundle accounting even when runtime preview html is not requested' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $manifest = (new OutputWriter())->saveMarkdownArtifactBoundary(
                $root,
                'review-no-preview.pdf',
                '![Known](0_image_0.png "stored")',
                ['0_image_0.png' => 'PNG'],
                ['scenario' => 'review-no-preview'],
                includeRuntimePreviewHtml: false
            );

            $bundle = $manifest['markdown_image_bundle'];
            $t->same(false, $manifest['runtime_preview']['requested']);
            $t->same(null, $manifest['runtime_preview']['html']);
            $t->same(0, $manifest['runtime_preview']['image_data_uri_count']);
            $t->same(1, $bundle['image_artifact_count']);
            $t->same(1, $bundle['markdown_reference_count']);
            $t->same(1, $bundle['embedded_reference_count']);
            $t->same(0, $bundle['missing_reference_count']);
            $t->same(0, $bundle['unreferenced_artifact_count']);
            $t->same(true, $bundle['markdown_preview_complete']);
            $t->same(false, $bundle['preview_html_requested']);
            $t->same(0, $bundle['preview_data_uri_count']);
            $t->same(null, $bundle['preview_data_uri_count_matches_embedded_references']);
            $t->same(['0_image_0.png' => 1], $bundle['target_reference_counts']);
            $t->same(['0_image_0.png' => 1], $bundle['embedded_reference_counts']);
            $t->same([], $bundle['missing_reference_counts']);
            $t->same([], $bundle['missing_markdown_image_targets']);
            $t->same([], $bundle['unreferenced_image_artifacts']);
        } finally {
            $removeTree($root);
        }
    },
];
