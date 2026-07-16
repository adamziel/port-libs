<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runId = bin2hex(random_bytes(4));
$input = sys_get_temp_dir() . '/markerpdf-empty-queue-handoff-input-' . $runId;
$output = sys_get_temp_dir() . '/markerpdf-empty-queue-handoff-output-' . $runId;
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
    foreach (['chapter-a.pdf', 'chapter-b.pdf'] as $filename) {
        file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% {$filename}\n%%EOF");
    }

    $batch = new BatchConverter();
    $cudaPlan = $batch->runtimeMainPreflightPlan(
        $input,
        $output,
        chunkIndex: 5,
        numChunks: 2,
        workers: 4,
        torchDevice: 'cuda',
        torchDeviceModel: 'cpu',
        modelSlots: ['layout-detector', null, 'ocr-recognizer']
    );
    $mpsPlan = $batch->runtimeMainPreflightPlan(
        $input,
        $output,
        chunkIndex: 5,
        numChunks: 2,
        workers: 4,
        torchDevice: 'mps',
        torchDeviceModel: 'cpu'
    );

    $cudaReview = $cudaPlan['worker_pool']['empty_task_queue_model_handoff'];
    $mpsReview = $mpsPlan['worker_pool']['empty_task_queue_model_handoff'];

    if (
        $cudaReview['review_reached'] !== true
        || $cudaReview['spawn_start_method_reached_before_empty_pool_failure'] !== true
        || $cudaReview['parent_load_all_models_before_empty_pool_failure'] !== true
        || $cudaReview['pool_creation_error_boundary'] !== 'pool-process-count-failed'
        || $cudaPlan['worker_pool']['pool_error_boundary'] !== 'empty-task-queue'
    ) {
        throw new RuntimeException('Expected empty CUDA/CPU queue to reach model handoff and then fail at Pool(processes=0).');
    }
    if (
        $mpsReview['review_reached'] !== true
        || $mpsReview['mps_worker_model_load_planned_before_empty_pool_failure'] !== true
        || $mpsReview['mps_worker_model_load_blocked_by_empty_pool'] !== true
        || $mpsPlan['worker_pool']['worker_initializer']['initializer_reached'] !== false
    ) {
        throw new RuntimeException('Expected empty MPS queue to defer worker model loading but block before worker_init.');
    }
    if (
        $cudaPlan['executes_python_or_models'] !== false
        || $cudaPlan['executes_multiprocessing'] !== false
        || $cudaPlan['executes_external_pdf_tools'] !== false
    ) {
        throw new RuntimeException('Empty-queue runtime smoke must not execute Python, multiprocessing, models, or external PDF tools.');
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-empty-queue-model-handoff-currentbase',
        'purpose' => 'Review convert.py empty chunk behavior before a WordPress PDF import queue launches Marker model workers.',
        'source_truth' => 'sddai/markerPDF convert.py computes total_processes, sets spawn, prepares model handoff, prints the summary, builds empty task_args, then fails Pool(processes=0).',
        'selected_filenames' => $cudaPlan['chunking']['selected_filenames'],
        'empty_queue_review_reached' => $cudaReview['review_reached'],
        'empty_queue_short_circuits_before_spawn' => $cudaReview['empty_queue_short_circuits_before_spawn'],
        'spawn_reached_before_empty_pool_failure' => $cudaReview['spawn_start_method_reached_before_empty_pool_failure'],
        'parent_loads_models_before_empty_pool_failure' => $cudaReview['parent_load_all_models_before_empty_pool_failure'],
        'parent_share_memory_before_empty_pool_failure' => $cudaReview['parent_share_memory_before_empty_pool_failure'],
        'share_memory_slot_indexes_before_empty_pool_failure' => $cudaReview['share_memory_model_slot_indexes_before_empty_pool_failure'],
        'none_model_slot_indexes_before_empty_pool_failure' => $cudaReview['none_model_slot_indexes_before_empty_pool_failure'],
        'conversion_summary_line' => $cudaReview['conversion_summary_line'],
        'worker_pool_error_boundary' => $cudaReview['worker_pool_error_boundary'],
        'pool_creation_error_boundary' => $cudaReview['pool_creation_error_boundary'],
        'pool_imap_reached' => $cudaReview['pool_imap_reached'],
        'worker_initializer_reached' => $cudaReview['worker_initializer_reached'],
        'cleanup_reached' => $cudaReview['cleanup_reached'],
        'mps_worker_model_load_planned_before_empty_pool_failure' => $mpsReview['mps_worker_model_load_planned_before_empty_pool_failure'],
        'mps_worker_model_load_blocked_by_empty_pool' => $mpsReview['mps_worker_model_load_blocked_by_empty_pool'],
        'executes_python_or_models' => $cudaPlan['executes_python_or_models'],
        'executes_multiprocessing' => $cudaPlan['executes_multiprocessing'],
        'executes_external_pdf_tools' => $cudaPlan['executes_external_pdf_tools'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    $removeTree($input);
    $removeTree($output);
}
