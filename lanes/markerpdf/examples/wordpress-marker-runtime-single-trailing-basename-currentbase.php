<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\SingleDocumentConverter;

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

$outputRoot = sys_get_temp_dir() . '/markerpdf-single-trailing-basename-smoke-' . bin2hex(random_bytes(4));
mkdir($outputRoot, 0777, true);

try {
    file_put_contents($outputRoot . DIRECTORY_SEPARATOR . '.md', 'previous trailing separator import');

    $converter = new SingleDocumentConverter();
    $plan = $converter->runtimePreflightPlan(
        '/wp-content/uploads/2026/editorial-checklist.pdf/',
        $outputRoot,
        maxPages: 1,
        startPage: 0,
        languages: 'English'
    );

    if ($plan['filename'] !== '' || $plan['output_policy']['empty_basename_after_trailing_separator'] !== true) {
        throw new RuntimeException('Expected convert_single.py os.path.basename boundary to preserve an empty output filename.');
    }
    if ($plan['output_policy']['existing_markdown'] !== true || $plan['output_policy']['skips_existing_markdown'] !== false) {
        throw new RuntimeException('Expected existing .md output to be observed without batch resume skipping.');
    }
    if ($plan['executes_python_or_models'] !== false || $plan['executes_external_pdf_tools'] !== false) {
        throw new RuntimeException('Single-document trailing basename smoke must not execute Python models or external tools.');
    }

    $result = $converter->convert(
        '/wp-content/uploads/2026/editorial-checklist.pdf/',
        $outputRoot,
        static fn (): array => [
            'text' => "<!-- wp:paragraph -->\n<p>Trailing separator import boundary.</p>\n<!-- /wp:paragraph -->",
            'images' => [],
            'metadata' => ['scenario' => 'wordpress-markerpdf-single-trailing-basename'],
        ],
        maxPages: 1,
        languages: 'English'
    );

    if ($result['filename'] !== '' || $result['markdown'] !== $outputRoot . DIRECTORY_SEPARATOR . '.md') {
        throw new RuntimeException('Expected supplied single-document conversion to write the upstream empty-basename Markdown path.');
    }
    if (!is_file($outputRoot . DIRECTORY_SEPARATOR . '_meta.json')) {
        throw new RuntimeException('Expected upstream empty-basename metadata path to be written.');
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-single-trailing-basename-currentbase',
        'purpose' => 'Review convert_single.py trailing-separator output basename behavior for WordPress single-PDF imports without launching Python, Torch, OCR, model workers, Streamlit, FastAPI, or external PDF tools.',
        'schema' => $plan['schema'],
        'filename' => $plan['filename'],
        'filepath' => $plan['filepath'],
        'markdown_path' => $plan['markdown_path'],
        'basename_source' => $plan['output_policy']['basename_source'],
        'empty_basename_after_trailing_separator' => $plan['output_policy']['empty_basename_after_trailing_separator'],
        'existing_markdown_seen' => $plan['output_policy']['existing_markdown'],
        'skips_existing_markdown' => $plan['output_policy']['skips_existing_markdown'],
        'converted_markdown' => $result['markdown'],
        'metadata_path' => $outputRoot . DIRECTORY_SEPARATOR . '_meta.json',
        'executes_python_or_models' => $plan['executes_python_or_models'],
        'executes_external_pdf_tools' => $plan['executes_external_pdf_tools'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($outputRoot);
}
