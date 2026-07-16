<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\OutputWriter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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

$outputRoot = sys_get_temp_dir() . '/markerpdf-output-document-basename-smoke-' . bin2hex(random_bytes(4));
$outsideStem = 'wordpress-output-escape-' . bin2hex(random_bytes(4));
$outsidePath = dirname($outputRoot) . DIRECTORY_SEPARATOR . $outsideStem;
$rawFilename = '..' . DIRECTORY_SEPARATOR . $outsideStem . '.pdf';

$removeTree($outputRoot);
$removeTree($outsidePath);

try {
    $manifest = (new OutputWriter())->saveMarkdownArtifactBoundary(
        $outputRoot,
        $rawFilename,
        "<!-- wp:paragraph -->\n<p>Direct markerPDF output artifact boundary.</p>\n<!-- /wp:paragraph -->",
        [],
        [
            'scenario' => 'wordpress-marker-runtime-output-document-basename-boundary-currentbase',
            'source_filename' => $rawFilename,
        ],
        includeRuntimePreviewHtml: false
    );

    $boundary = $manifest['document_filename_boundary'];
    $markdownPath = (string) $manifest['markdown_artifact']['path'];
    $metadataPath = (string) $manifest['metadata_artifact']['path'];

    if (!is_file($markdownPath) || !is_file($metadataPath)) {
        throw new RuntimeException('Expected basename-bounded Markdown and metadata artifacts to be written.');
    }
    if (is_dir($outsidePath)) {
        throw new RuntimeException('Direct output artifact write escaped the configured output root.');
    }
    if (($boundary['native_safe_basename'] ?? null) !== $outsideStem . '.pdf') {
        throw new RuntimeException('Expected document filename boundary to use the upstream basename.');
    }
    if (($manifest['executes_python_or_models'] ?? true) || ($manifest['executes_external_pdf_tools'] ?? true)) {
        throw new RuntimeException('Document filename output boundary must not execute models or external PDF tools.');
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-output-document-basename-boundary-currentbase',
        'purpose' => 'Review marker.output save_markdown document filename basename handling for direct WordPress artifact handoff without launching Python, Torch, OCR, model workers, Streamlit, FastAPI, or external PDF tools.',
        'source' => $manifest['source'],
        'upstream_boundary' => $boundary['upstream_boundary'],
        'raw_filename' => $boundary['raw_filename'],
        'native_safe_basename' => $boundary['native_safe_basename'],
        'path_segments_removed_for_native_output_paths' => $boundary['path_segments_removed_for_native_output_paths'],
        'subfolder_prefixed_by_output_folder' => $boundary['subfolder_prefixed_by_output_folder'],
        'markdown_exists' => is_file($markdownPath),
        'metadata_exists' => is_file($metadataPath),
        'outside_output_path_exists' => is_dir($outsidePath),
        'executes_streamlit' => $manifest['executes_streamlit'],
        'executes_pdfium' => $manifest['executes_pdfium'],
        'executes_python_or_models' => $manifest['executes_python_or_models'],
        'executes_external_pdf_tools' => $manifest['executes_external_pdf_tools'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($outputRoot);
    $removeTree($outsidePath);
}
