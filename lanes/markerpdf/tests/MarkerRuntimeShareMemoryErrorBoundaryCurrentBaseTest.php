<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-runtime-share-memory-error-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf share-memory error folder.');
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
    'records parent model share_memory failures before summary task args and pool launch' => static function (
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
            $t->same('convert.py load_all_models share_memory slot boundary', $review['source']);
            $t->same('after_load_all_models_before_conversion_summary', $review['order']);
            $t->same(true, $review['review_reached']);
            $t->same(null, $review['blocked_by']);
            $t->same('load_all_models()', $review['model_list_source']);
            $t->same('model_lst', $review['model_list_value']);
            $t->same(true, $review['model_slot_fixture_used']);
            $t->same(4, $review['model_slot_count']);
            $t->same([
                [
                    'index' => 0,
                    'label' => 'layout-detector',
                    'is_none' => false,
                    'share_memory_called' => true,
                    'share_memory_success' => true,
                    'blocked_by_previous_share_memory_error' => false,
                ],
                [
                    'index' => 1,
                    'label' => null,
                    'is_none' => true,
                    'share_memory_called' => false,
                    'share_memory_success' => null,
                    'blocked_by_previous_share_memory_error' => false,
                ],
                [
                    'index' => 2,
                    'label' => 'ocr-recognizer',
                    'is_none' => false,
                    'share_memory_called' => true,
                    'share_memory_success' => false,
                    'blocked_by_previous_share_memory_error' => false,
                    'share_memory_error_boundary' => 'model-share-memory-failed',
                    'share_memory_error_class' => 'RuntimeError',
                    'share_memory_error_message' => 'CUDA shared memory unavailable for OCR recognizer',
                ],
                [
                    'index' => 3,
                    'label' => 'table-recognizer',
                    'is_none' => false,
                    'share_memory_called' => false,
                    'share_memory_success' => null,
                    'blocked_by_previous_share_memory_error' => true,
                ],
            ], $review['model_slots']);
            $t->same([1], $review['none_model_slot_indexes']);
            $t->same([0, 2], $review['share_memory_model_slot_indexes']);
            $t->same([0], $review['share_memory_successful_model_slot_indexes']);
            $t->same([2], $review['share_memory_error_slot_indexes']);
            $t->same(2, $review['share_memory_call_count']);
            $t->same(true, $review['share_memory_error_found']);
            $t->same(2, $review['first_share_memory_error_index']);
            $t->same('ocr-recognizer', $review['first_share_memory_error_label']);
            $t->same('RuntimeError', $review['first_share_memory_error_class']);
            $t->same('CUDA shared memory unavailable for OCR recognizer', $review['first_share_memory_error_message']);
            $t->same(true, $review['share_memory_loop_stops_on_first_error']);
            $t->same([3], $review['model_slots_after_first_error_not_called']);
            $t->same(true, $review['blocks_conversion_summary']);
            $t->same(true, $review['blocks_task_args']);
            $t->same(false, $review['executes_python_or_models']);

            $t->same(true, $plan['spawn_start_method']['start_method_success']);
            $t->same(true, $plan['model_handoff']['model_handoff_reached']);
            $t->same(false, $plan['model_handoff']['model_handoff_success']);
            $t->same('model-share-memory-failed', $plan['model_handoff']['model_handoff_error_boundary']);
            $t->same(true, $plan['model_handoff']['main_load_all_models']);
            $t->same(true, $plan['model_handoff']['share_memory_before_pool']);
            $t->same(null, $plan['model_handoff']['worker_init_argument']);
            $selectedMetadataFilenames = $plan['metadata']['selected_metadata_filenames'];
            sort($selectedMetadataFilenames, SORT_STRING);
            $t->same(true, $plan['metadata']['metadata_load_success']);
            $t->same(['alpha.pdf', 'beta.pdf'], $selectedMetadataFilenames);
            $t->same(false, $plan['console_summary']['summary_reached']);
            $t->same('model-share-memory-failed', $plan['console_summary']['blocked_by']);
            $t->same(0, $plan['worker_pool']['task_args_count']);
            $t->same([], $plan['worker_pool']['task_args']);
            $t->same(false, $plan['worker_pool']['pool_launchable']);
            $t->same('model-share-memory-failed', $plan['worker_pool']['pool_error_boundary']);
            $t->same(false, $plan['executes_python_or_models']);
            $t->same(false, $plan['executes_multiprocessing']);
            $t->same(false, $plan['executes_external_pdf_tools']);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
];
