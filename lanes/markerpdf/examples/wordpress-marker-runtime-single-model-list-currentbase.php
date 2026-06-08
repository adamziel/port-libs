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

$outputRoot = sys_get_temp_dir() . '/markerpdf-single-model-list-smoke-' . bin2hex(random_bytes(4));
if (!mkdir($outputRoot, 0777, true) && !is_dir($outputRoot)) {
    throw new RuntimeException('Unable to create markerpdf single-model-list smoke folder.');
}

try {
    $plan = (new SingleDocumentConverter())->runtimePreflightPlan(
        '/wp/uploads/2026/editorial-model-order.pdf',
        $outputRoot,
        maxPages: 4,
        startPage: 0,
        languages: 'English,French',
        batchMultiplier: 2
    );

    $modelSequence = $plan['model_load_sequence'];
    $expectedOrder = ['texify', 'layout', 'order', 'detection', 'ocr', 'table_model'];
    if ($modelSequence['model_slot_order'] !== $expectedOrder) {
        throw new RuntimeException('Unexpected convert_single.py model slot order.');
    }
    if ($plan['conversion_call']['model_argument_source'] !== 'model_lst returned by load_all_models()') {
        throw new RuntimeException('Expected convert_single_pdf to receive the upstream model_lst.');
    }
    if ($plan['executes_python_or_models'] !== false || $modelSequence['executes_python_or_models'] !== false) {
        throw new RuntimeException('Single-document model-list smoke must not execute Python or models.');
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-single-model-list-currentbase',
        'purpose' => 'Review the upstream convert_single.py load_all_models model-list handoff for a WordPress single-PDF import without running Python, Torch, OCR, Surya, Texify, multiprocessing, or external PDF tools.',
        'schema' => $plan['schema'],
        'model_sequence_source' => $modelSequence['source'],
        'model_construction_order' => $modelSequence['construction_order'],
        'model_slot_order' => $modelSequence['model_slot_order'],
        'model_slot_count' => $modelSequence['model_count'],
        'recognition_model_always_loaded_for_single_document' => $plan['model_boundary']['recognition_model_always_loaded_for_single_document'],
        'single_document_share_memory_loop' => $plan['model_boundary']['single_document_share_memory_loop'],
        'conversion_model_argument_source' => $plan['conversion_call']['model_argument_source'],
        'executes_python_or_models' => $plan['executes_python_or_models'],
        'executes_multiprocessing' => $plan['executes_multiprocessing'],
        'executes_external_pdf_tools' => $plan['executes_external_pdf_tools'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($outputRoot);
}
