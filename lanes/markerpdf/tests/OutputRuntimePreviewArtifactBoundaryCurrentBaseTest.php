<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\OutputWriter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-output-runtime-preview-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf output-runtime-preview test folder.');
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
    'persists output artifacts and exposes runtime-only preview html without leaking image payloads' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $writer = new OutputWriter();
            $unsafeImage = '../cover preview?.jpg';
            $markdown = <<<'MD'
<!-- wp:paragraph -->
<p>Imported markerPDF output handoff.</p>
<!-- /wp:paragraph -->

![Cover preview](../cover preview?.jpg)
![Cover preview duplicate](../cover preview?.jpg)
![Missing preview](missing.png)

<!-- wp:image -->
<figure class="wp-block-image"><img src="../cover preview?.jpg" alt="../cover preview?.jpg"/></figure>
<!-- /wp:image -->
MD;

            $manifest = $writer->saveMarkdownArtifactBoundary(
                $root,
                'runtime-preview-artifact.pdf',
                $markdown,
                [
                    $unsafeImage => 'PNG-COVER',
                    '0_image_0.png' => ['bytes' => 'PNG-SUPPLEMENT'],
                ],
                [
                    'scenario' => 'wordpress-marker-runtime-preview-artifact-boundary-currentbase',
                    'images' => [$unsafeImage, '0_image_0.png'],
                    'image_map' => [
                        $unsafeImage => ['filename' => $unsafeImage, 'role' => 'preview'],
                    ],
                ]
            );

            $subfolder = $root . DIRECTORY_SEPARATOR . 'runtime-preview-artifact';
            $markdownPath = $subfolder . DIRECTORY_SEPARATOR . 'runtime-preview-artifact.md';
            $metadataPath = $subfolder . DIRECTORY_SEPARATOR . 'runtime-preview-artifact_meta.json';
            $markdownOut = (string) file_get_contents($markdownPath);
            $metadataOut = (string) file_get_contents($metadataPath);

            $t->same('marker_output_runtime_preview_artifact_boundary', $manifest['source']);
            $t->same('marker.output.save_markdown + marker_app.markdown_insert_images', $manifest['upstream_boundary']);
            $t->same($subfolder, $manifest['subfolder']);
            $t->same('runtime-preview-artifact.md', $manifest['markdown_artifact']['filename']);
            $t->same('runtime-preview-artifact_meta.json', $manifest['metadata_artifact']['filename']);
            $t->same('markdown', $manifest['markdown_artifact']['format']);
            $t->same('json', $manifest['metadata_artifact']['format']);
            $t->same(true, $manifest['markdown_artifact']['visible_text_artifact']);
            $t->same(false, $manifest['markdown_artifact']['runtime_preview_inlined']);
            $t->same(true, $manifest['metadata_artifact']['review_only']);
            $t->same(true, $manifest['metadata_artifact']['payload_separated_from_visible_markdown']);
            $t->same(true, is_file($markdownPath));
            $t->same(true, is_file($metadataPath));
            $t->same('PNG-COVER', file_get_contents($subfolder . DIRECTORY_SEPARATOR . 'cover_preview.png'));
            $t->same('PNG-SUPPLEMENT', file_get_contents($subfolder . DIRECTORY_SEPARATOR . '0_image_0.png'));
            $t->contains('![Cover preview](cover_preview.png)', $markdownOut);
            $t->contains('![Cover preview duplicate](cover_preview.png)', $markdownOut);
            $t->contains('![Missing preview](missing.png)', $markdownOut);
            $t->contains('src="cover_preview.png"', $markdownOut);
            $t->contains('alt="cover_preview.png"', $markdownOut);
            $t->true(!str_contains($markdownOut, $unsafeImage));
            $t->true(!str_contains($markdownOut, 'PNG-COVER'));
            $t->true(!str_contains($markdownOut, 'data:image/png;base64,'));
            $t->contains('"cover_preview.png"', $metadataOut);
            $t->true(!str_contains($metadataOut, $unsafeImage));
            $t->true(!str_contains($metadataOut, 'PNG-COVER'));
            $t->same([$unsafeImage => 'cover_preview.png'], $manifest['image_name_map']);

            $imageByName = [];
            foreach ($manifest['image_artifacts'] as $artifact) {
                $imageByName[$artifact['filename']] = $artifact;
            }

            $t->same($unsafeImage, $imageByName['cover_preview.png']['source_filename']);
            $t->same(true, $imageByName['cover_preview.png']['source_filename_rewritten']);
            $t->same('image/png', $imageByName['cover_preview.png']['mime_type']);
            $t->same(9, $imageByName['cover_preview.png']['size']);
            $t->same(hash('sha256', 'PNG-COVER'), $imageByName['cover_preview.png']['sha256']);
            $t->same(true, $imageByName['cover_preview.png']['persisted_to_output_folder']);
            $t->same(true, $imageByName['cover_preview.png']['runtime_preview_embeddable']);
            $t->same(false, $imageByName['0_image_0.png']['source_filename_rewritten']);

            $preview = $manifest['runtime_preview'];
            $t->same(true, $preview['requested']);
            $t->same(true, $preview['runtime_only']);
            $t->same(false, $preview['persisted_to_output_folder']);
            $t->same(true, $preview['uses_persisted_image_bytes']);
            $t->same(true, $preview['markdown_keeps_file_references']);
            $t->same(['cover_preview.png', 'cover_preview.png', 'missing.png'], $preview['markdown_image_targets']);
            $t->same(['cover_preview.png', 'cover_preview.png'], $preview['embedded_image_targets']);
            $t->same(['missing.png'], $preview['unembedded_markdown_image_targets']);
            $t->same(2, $preview['image_data_uri_count']);
            $t->same(hash('sha256', (string) $preview['html']), $preview['html_sha256']);
            $t->contains('data:image/png;base64,UE5HLUNPVkVS', (string) $preview['html']);
            $t->contains('alt="Cover preview"', (string) $preview['html']);
            $t->contains('alt="Cover preview duplicate"', (string) $preview['html']);
            $t->contains('![Missing preview](missing.png)', (string) $preview['html']);
            $t->contains('src="cover_preview.png"', (string) $preview['html']);
            $t->true(!str_contains((string) $preview['html'], $unsafeImage));
            $t->same(false, $manifest['executes_streamlit']);
            $t->same(false, $manifest['executes_pdfium']);
            $t->same(false, $manifest['executes_python_or_models']);
            $t->same(false, $manifest['executes_external_pdf_tools']);
        } finally {
            $removeTree($root);
        }
    },
    'can report saved output artifacts without building a runtime preview html payload' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $manifest = (new OutputWriter())->saveMarkdownArtifactBoundary(
                $root,
                'review-only.pdf',
                'No inline preview requested.',
                ['0_image_0.png' => 'PNG'],
                ['scenario' => 'review-only'],
                includeRuntimePreviewHtml: false
            );

            $t->same(false, $manifest['runtime_preview']['requested']);
            $t->same(null, $manifest['runtime_preview']['html']);
            $t->same(null, $manifest['runtime_preview']['html_sha256']);
            $t->same(0, $manifest['runtime_preview']['html_size']);
            $t->same(0, $manifest['runtime_preview']['image_data_uri_count']);
            $t->same([], $manifest['runtime_preview']['markdown_image_targets']);
            $t->same([], $manifest['runtime_preview']['embedded_image_targets']);
            $t->same([], $manifest['runtime_preview']['unembedded_markdown_image_targets']);
            $t->same('PNG', file_get_contents($manifest['image_artifacts'][0]['path']));
            $t->same(hash('sha256', 'No inline preview requested.'), $manifest['markdown_artifact']['sha256']);
            $t->same(true, $manifest['metadata_artifact']['exists']);
        } finally {
            $removeTree($root);
        }
    },
];
