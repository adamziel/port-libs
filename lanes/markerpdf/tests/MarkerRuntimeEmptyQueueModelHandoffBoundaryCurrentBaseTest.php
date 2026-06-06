<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-runtime-empty-queue-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf empty-queue runtime folder.');
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
        if (is_link($child) || !is_dir($child)) {
            unlink($child);
        } else {
            $removeTree($child);
        }
    }

    rmdir($path);
};

return [
    'records empty convert.py chunks reaching spawn model handoff summary and then Pool failure' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        try {
            foreach (['alpha.pdf', 'beta.pdf'] as $filename) {
                file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% {$filename}\n%%EOF");
            }

            $plan = (new BatchConverter())->runtimeMainPreflightPlan(
                $input,
                $output,
                chunkIndex: 5,
                numChunks: 2,
                workers: 4,
                torchDevice: 'cuda',
                torchDeviceModel: 'cpu',
                modelSlots: ['layout-detector', null, 'ocr-recognizer']
            );

            $review = $plan['worker_pool']['empty_task_queue_model_handoff'];
            $t->same('convert.py empty files_to_convert model handoff boundary', $review['source']);
            $t->same('after_metadata_spawn_model_handoff_summary_and_task_args_before_pool_creation_error', $review['order']);
            $t->same(true, $review['review_reached']);
            $t->same(null, $review['blocked_by']);
            $t->same(0, $review['selected_count']);
            $t->same(0, $review['task_args_count']);
            $t->same(true, $review['empty_files_to_convert']);
            $t->same(false, $review['empty_queue_short_circuits_before_spawn']);
            $t->same(true, $review['spawn_start_method_reached_before_empty_pool_failure']);
            $t->same(true, $review['spawn_start_method_success_before_empty_pool_failure']);
            $t->same(0, $review['total_processes_computed_before_spawn']);
            $t->same(true, $review['model_handoff_reached_before_empty_pool_failure']);
            $t->same(true, $review['parent_load_all_models_before_empty_pool_failure']);
            $t->same(true, $review['parent_share_memory_before_empty_pool_failure']);
            $t->same(true, $review['share_memory_loop_reached_before_empty_pool_failure']);
            $t->same([0, 2], $review['share_memory_model_slot_indexes_before_empty_pool_failure']);
            $t->same([1], $review['none_model_slot_indexes_before_empty_pool_failure']);
            $t->same(false, $review['mps_worker_model_load_planned_before_empty_pool_failure']);
            $t->same(false, $review['mps_worker_model_load_blocked_by_empty_pool']);
            $t->same(true, $review['conversion_summary_reached_before_empty_pool_failure']);
            $t->same(
                'Converting 0 pdfs in chunk 6/2 with 0 processes, and storing in ' . $output,
                $review['conversion_summary_line']
            );
            $t->same(true, $review['task_args_built_before_empty_pool_failure']);
            $t->same(true, $review['pool_creation_reached_after_empty_summary']);
            $t->same('empty-task-queue', $review['worker_pool_error_boundary']);
            $t->same('pool-process-count-failed', $review['pool_creation_error_boundary']);
            $t->same(false, $review['pool_imap_reached']);
            $t->same(false, $review['worker_initializer_reached']);
            $t->same(false, $review['cleanup_reached']);
            $t->same(false, $review['executes_python_or_models']);
            $t->same(false, $review['executes_multiprocessing']);
            $t->same(false, $review['executes_external_pdf_tools']);

            $t->same([], $plan['worker_pool']['task_args']);
            $t->same(0, $plan['worker_pool']['total_processes']);
            $t->same('empty-task-queue', $plan['worker_pool']['pool_error_boundary']);
            $t->same('pool-process-count-failed', $plan['worker_pool']['pool_creation']['error_boundary']);
            $t->same(true, $plan['model_handoff']['main_load_all_models']);
            $t->same(true, $plan['console_summary']['summary_reached']);
            $t->same(false, $plan['executes_python_or_models']);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
    'records empty MPS chunks defer model loading but still block before worker init' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        try {
            file_put_contents($input . DIRECTORY_SEPARATOR . 'only.pdf', "%PDF-1.4\n% only\n%%EOF");

            $plan = (new BatchConverter())->runtimeMainPreflightPlan(
                $input,
                $output,
                chunkIndex: -1,
                numChunks: 2,
                workers: 4,
                torchDevice: 'mps',
                torchDeviceModel: 'cpu'
            );

            $review = $plan['worker_pool']['empty_task_queue_model_handoff'];
            $t->same(true, $review['review_reached']);
            $t->same(true, $review['model_handoff_reached_before_empty_pool_failure']);
            $t->same(false, $review['parent_load_all_models_before_empty_pool_failure']);
            $t->same(false, $review['parent_share_memory_before_empty_pool_failure']);
            $t->same(false, $review['share_memory_loop_reached_before_empty_pool_failure']);
            $t->same([], $review['share_memory_model_slot_indexes_before_empty_pool_failure']);
            $t->same(true, $review['mps_worker_model_load_planned_before_empty_pool_failure']);
            $t->same(true, $review['mps_worker_model_load_blocked_by_empty_pool']);
            $t->same('pool-process-count-failed', $review['pool_creation_error_boundary']);
            $t->same(false, $review['worker_initializer_reached']);
            $t->same(false, $review['executes_python_or_models']);
            $t->same(false, $plan['worker_pool']['worker_initializer']['initializer_reached']);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
    'does not mark nonempty selected chunks as empty queue handoff reviews' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        try {
            file_put_contents($input . DIRECTORY_SEPARATOR . 'ready.pdf', "%PDF-1.4\n% ready\n%%EOF");

            $plan = (new BatchConverter())->runtimeMainPreflightPlan(
                $input,
                $output,
                workers: 4,
                torchDevice: 'cuda',
                torchDeviceModel: 'cpu'
            );

            $review = $plan['worker_pool']['empty_task_queue_model_handoff'];
            $t->same(false, $review['review_reached']);
            $t->same('not-empty-task-queue', $review['blocked_by']);
            $t->same(1, $review['selected_count']);
            $t->same(1, $review['task_args_count']);
            $t->same(false, $review['empty_files_to_convert']);
            $t->same(null, $review['pool_creation_error_boundary']);
            $t->same(true, $plan['worker_pool']['pool_launchable']);
            $t->same(false, $plan['executes_python_or_models']);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
];
