<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\OutputWriter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-output-document-basename-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerPDF output basename test folder.');
    }

    return $path;
};

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!file_exists($path) && !is_link($path)) {
        return;
    }
    if (is_link($path) || !is_dir($path)) {
        unlink($path);
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $removeTree($path . DIRECTORY_SEPARATOR . $entry);
    }

    rmdir($path);
};

return [
    'uses upstream document basename for direct output artifact paths' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        $outsideStem = 'escaped-output-' . bin2hex(random_bytes(4));
        $outsidePath = dirname($root) . DIRECTORY_SEPARATOR . $outsideStem;
        $rawFilename = '..' . DIRECTORY_SEPARATOR . $outsideStem . '.pdf';

        $removeTree($outsidePath);
        try {
            $writer = new OutputWriter();
            $manifest = $writer->saveMarkdownArtifactBoundary(
                $root,
                $rawFilename,
                "<!-- wp:paragraph -->\n<p>Direct output basename boundary.</p>\n<!-- /wp:paragraph -->",
                [],
                ['scenario' => 'wordpress-markerpdf-output-document-basename-boundary'],
                includeRuntimePreviewHtml: false
            );

            $expectedSubfolder = $root . DIRECTORY_SEPARATOR . $outsideStem;
            $expectedMarkdown = $expectedSubfolder . DIRECTORY_SEPARATOR . $outsideStem . '.md';
            $expectedMetadata = $expectedSubfolder . DIRECTORY_SEPARATOR . $outsideStem . '_meta.json';
            $boundary = $manifest['document_filename_boundary'];

            $t->same($expectedSubfolder, $writer->getSubfolderPath($root, $rawFilename));
            $t->same($expectedMarkdown, $writer->getMarkdownFilepath($root, $rawFilename));
            $t->same($expectedSubfolder, $manifest['subfolder']);
            $t->same($expectedMarkdown, $manifest['markdown_artifact']['path']);
            $t->same($expectedMetadata, $manifest['metadata_artifact']['path']);
            $t->same($rawFilename, $manifest['filename']);
            $t->same('marker_output_document_filename_basename_boundary', $boundary['source']);
            $t->same($rawFilename, $boundary['raw_filename']);
            $t->same($outsideStem . '.pdf', $boundary['native_safe_basename']);
            $t->same($outsideStem, $boundary['native_safe_stem']);
            $t->same(true, $boundary['raw_has_path_segments']);
            $t->same(true, $boundary['path_segments_removed_for_native_output_paths']);
            $t->same(true, $boundary['uses_basename_for_subfolder']);
            $t->same(true, $boundary['uses_basename_for_markdown_filename']);
            $t->same(true, $boundary['subfolder_prefixed_by_output_folder']);
            $t->same(false, $boundary['executes_python_or_models']);
            $t->same(false, $boundary['executes_external_pdf_tools']);
            $t->true($writer->markdownExists($root, $rawFilename));
            $t->true($writer->markdownExists($root, $outsideStem . '.pdf'));
            $t->true(is_file($expectedMarkdown));
            $t->true(is_file($expectedMetadata));
            $t->true(!is_dir($outsidePath));
            $t->true(!is_file($outsidePath . DIRECTORY_SEPARATOR . $outsideStem . '.md'));
        } finally {
            $removeTree($root);
            $removeTree($outsidePath);
        }
    },
    'normalizes windows-style document path segments without changing empty basenames' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $writer = new OutputWriter();

            $t->same(
                $root . DIRECTORY_SEPARATOR . 'wordpress-windows-upload',
                $writer->getSubfolderPath($root, 'C:\\wp-content\\uploads\\2026\\wordpress-windows-upload.pdf')
            );
            $t->same(
                $root . DIRECTORY_SEPARATOR . 'wordpress-windows-upload' . DIRECTORY_SEPARATOR . 'wordpress-windows-upload.md',
                $writer->getMarkdownFilepath($root, 'C:\\wp-content\\uploads\\2026\\wordpress-windows-upload.pdf')
            );
            $t->same($root . DIRECTORY_SEPARATOR, $writer->getSubfolderPath($root, ''));
            $t->same($root . DIRECTORY_SEPARATOR . '.md', $writer->getMarkdownFilepath($root, ''));
        } finally {
            $removeTree($root);
        }
    },
];
