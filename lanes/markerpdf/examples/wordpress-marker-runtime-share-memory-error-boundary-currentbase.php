<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runId = bin2hex(random_bytes(4));
$input = sys_get_temp_dir() . '/markerpdf-share-memory-error-input-' . $runId;
$output = sys_get_temp_dir() . '/markerpdf-share-memory-error-output-' . $runId;
@mkdir($input, 0777, true);
@mkdir($output, 0777, true);

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $child = $path . DIRECTORY_SEPARATOR . $entry;
        if (is_link($child) || !is_dir($child)) {
            unlink($child);
        } else {
            $removeTree($child);
        }
    }

    rmdir($path);
};

try {
    foreach (['alpha.pdf', 'beta.pdf'] as $filename) {
        file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% {$filename}\n%%EOF");
    }

    $plan = (new BatchConverter())->runtimeMainPreflightPlan(
        $input,
        $output,
        workers: 4,
        metadataByFilename: [
            'alpha.pdf' => ['title' => 'Alpha Import'],
            'beta.pdf' => ['title' => 'Beta Import'],
        ],
        torchDevice: 'cuda',
        torchDeviceModel: 'cpu',
        modelSlots: [
            'layout-detector',
            null,
            [
                'label' => 'ocr-recognizer',
                'share_memory_error_class' => 'RuntimeError',
                'share_memory_error' => 'CUDA shared memory unavailable for OCR recognizer',
            ],
            'table-recognizer',
        ]
    );

    $review = $plan['model_handoff']['model_share_memory_review'];
    if (
        $review['share_memory_error_found'] !== true
        || $review['first_share_memory_error_index'] !== 2
        || $review['model_slots_after_first_error_not_called'] !== [3]
        || $plan['console_summary']['summary_reached'] !== false
        || $plan['worker_pool']['task_args_count'] !== 0
        || $plan['worker_pool']['pool_launchable'] !== false
    ) {
        throw new RuntimeException('Expected convert.py share_memory failure to block before summary, task args, and Pool launch.');
    }
    if (
        $plan['executes_python_or_models'] !== false
        || $plan['executes_multiprocessing'] !== false
        || $plan['executes_external_pdf_tools'] !== false
    ) {
        throw new RuntimeException('Runtime share_memory error smoke must not execute Python, models, multiprocessing, or external PDF tools.');
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-share-memory-error-boundary-currentbase',
        'purpose' => 'Review convert.py parent model share_memory failures before a WordPress PDF import queue builds task args or launches workers.',
        'source_truth' => 'sddai/markerPDF convert.py calls model.share_memory() for each non-None parent model before printing the conversion summary, building task_args, or entering mp.Pool.',
        'selected_filenames' => $plan['chunking']['selected_filenames'],
        'metadata_loaded_before_model_handoff' => $plan['metadata']['metadata_load_success'],
        'share_memory_review_reached' => $review['review_reached'],
        'share_memory_error_found' => $review['share_memory_error_found'],
        'share_memory_error_slot_indexes' => $review['share_memory_error_slot_indexes'],
        'first_share_memory_error_index' => $review['first_share_memory_error_index'],
        'first_share_memory_error_label' => $review['first_share_memory_error_label'],
        'first_share_memory_error_class' => $review['first_share_memory_error_class'],
        'first_share_memory_error_message' => $review['first_share_memory_error_message'],
        'model_slots_after_first_error_not_called' => $review['model_slots_after_first_error_not_called'],
        'conversion_summary_reached' => $plan['console_summary']['summary_reached'],
        'worker_pool_error_boundary' => $plan['worker_pool']['pool_error_boundary'],
        'task_args_count' => $plan['worker_pool']['task_args_count'],
        'pool_launchable' => $plan['worker_pool']['pool_launchable'],
        'executes_python_or_models' => $plan['executes_python_or_models'],
        'executes_multiprocessing' => $plan['executes_multiprocessing'],
        'executes_external_pdf_tools' => $plan['executes_external_pdf_tools'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    $removeTree($input);
    $removeTree($output);
}
