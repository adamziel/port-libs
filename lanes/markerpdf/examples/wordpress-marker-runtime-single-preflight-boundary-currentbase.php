<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\OutputWriter;
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

$outputRoot = sys_get_temp_dir() . '/markerpdf-runtime-preflight-smoke-' . bin2hex(random_bytes(4));

try {
    (new OutputWriter())->saveMarkdown(
        $outputRoot,
        'editorial-checklist.pdf',
        '<!-- wp:paragraph --><p>Previously imported checklist.</p><!-- /wp:paragraph -->',
        [],
        ['title' => 'Previously Imported Checklist']
    );

    $plan = (new SingleDocumentConverter())->runtimePreflightPlan(
        '/wp/uploads/editorial-checklist.pdf',
        $outputRoot,
        maxPages: 2,
        startPage: 0,
        languages: 'English,Spanish',
        batchMultiplier: 3
    );

    if ($plan['executes_python_or_models'] !== false || $plan['model_boundary']['native_plan_loads_models'] !== false) {
        throw new RuntimeException('Runtime preflight boundary smoke must not execute upstream models.');
    }
    if ($plan['output_policy']['existing_markdown'] !== true || $plan['output_policy']['skips_existing_markdown'] !== false) {
        throw new RuntimeException('Expected convert_single.py preflight to record existing output without applying batch resume skip.');
    }
    if ($plan['output_policy']['saves_empty_output'] !== true || $plan['output_policy']['min_length_preflight'] !== false) {
        throw new RuntimeException('Expected convert_single.py preflight to preserve empty-output save policy without min_length gating.');
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-single-preflight-boundary-currentbase',
        'purpose' => 'Record convert_single.py model-load, option, and save_markdown admission boundaries for a WordPress single-PDF import without launching Python, Torch, pdftext, pypdfium, model workers, Streamlit, FastAPI, or external PDF tools.',
        'schema' => $plan['schema'],
        'filename' => $plan['filename'],
        'options' => $plan['options'],
        'preflight_order' => $plan['preflight_order'],
        'model_boundary' => $plan['model_boundary'],
        'output_policy' => $plan['output_policy'],
        'executes_python_or_models' => $plan['executes_python_or_models'],
        'executes_external_pdf_tools' => $plan['executes_external_pdf_tools'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($outputRoot);
}
