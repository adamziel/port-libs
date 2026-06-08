<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-runtime-model-list-arity-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf model-list arity folder.');
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
    'records convert_single_pdf model list arity errors without blocking pool admission' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        try {
            file_put_contents($input . DIRECTORY_SEPARATOR . 'partial-models.pdf', "%PDF-1.4\n% partial\n%%EOF");

            $batch = new BatchConverter();
            $partial = $batch->runtimeMainPreflightPlan(
                $input,
                $output,
                workers: 3,
                torchDevice: 'cuda',
                torchDeviceModel: 'cpu',
                modelSlots: [
                    'texify',
                    'layout',
                    'order',
                    'detection',
                    'ocr',
                ]
            );
            $overfull = $batch->runtimeMainPreflightPlan(
                $input,
                $output,
                workers: 3,
                torchDevice: 'cuda',
                torchDeviceModel: 'cpu',
                modelSlots: [
                    'texify',
                    'layout',
                    'order',
                    'detection',
                    'ocr',
                    'table_model',
                    'extra-model',
                ]
            );

            $partialHandoff = $partial['model_handoff'];
            $partialShareMemory = $partialHandoff['model_share_memory_review'];
            $partialInitializer = $partial['worker_pool']['worker_initializer'];

            $t->same('convert.py settings.TORCH_DEVICE model handoff', $partialHandoff['source']);
            $t->same(true, $partialHandoff['model_handoff_reached']);
            $t->same(true, $partialHandoff['model_handoff_success']);
            $t->same(false, $partialHandoff['blocks_conversion_summary']);
            $t->same(false, $partialHandoff['blocks_task_args']);
            $t->same('model_lst', $partialHandoff['worker_init_argument']);
            $t->same(6, $partialHandoff['model_slot_expected_count']);
            $t->same(5, $partialHandoff['model_slot_count']);
            $t->same(false, $partialHandoff['model_list_arity_matches_convert_single_pdf_unpack']);
            $t->same('convert-single-pdf-model-unpack-failed', $partialHandoff['model_list_arity_error_boundary']);
            $t->same('ValueError', $partialHandoff['model_list_arity_error_class']);
            $t->same('not enough values to unpack (expected 6, got 5)', $partialHandoff['model_list_arity_error_message']);
            $t->same(true, $partialHandoff['model_list_arity_error_caught_by_process_single_pdf']);
            $t->same(true, $partialHandoff['model_list_arity_not_checked_before_pool_launch']);
            $t->same(false, $partialHandoff['executes_python_or_models']);

            $t->same('convert.py load_all_models share_memory slot boundary', $partialShareMemory['source']);
            $t->same(true, $partialShareMemory['review_reached']);
            $t->same(6, $partialShareMemory['model_slot_expected_count']);
            $t->same(5, $partialShareMemory['model_slot_count']);
            $t->same(false, $partialShareMemory['model_slot_count_matches_convert_single_pdf_unpack']);
            $t->same('convert-single-pdf-model-unpack-failed', $partialShareMemory['model_list_arity_error_boundary']);
            $t->same('ValueError', $partialShareMemory['model_list_arity_error_class']);
            $t->same('not enough values to unpack (expected 6, got 5)', $partialShareMemory['model_list_arity_error_message']);
            $t->same('convert_single_pdf model_lst unpack', $partialShareMemory['model_list_arity_error_stage']);
            $t->same(true, $partialShareMemory['model_list_arity_error_caught_by_process_single_pdf']);
            $t->same([0, 1, 2, 3, 4], $partialShareMemory['share_memory_model_slot_indexes']);
            $t->same(false, $partialShareMemory['share_memory_error_found']);
            $t->same(false, $partialShareMemory['blocks_conversion_summary']);
            $t->same(false, $partialShareMemory['blocks_task_args']);

            $t->same(true, $partial['console_summary']['summary_reached']);
            $t->same(1, $partial['worker_pool']['task_args_count']);
            $t->same(1, $partial['worker_pool']['total_processes']);
            $t->same(true, $partial['worker_pool']['pool_launchable']);
            $t->same(null, $partial['worker_pool']['pool_error_boundary']);
            $t->same(true, $partialInitializer['initializer_reached']);
            $t->same('model_lst', $partialInitializer['shared_model_value']);
            $t->same('parent-shared-model-list', $partialInitializer['model_refs_source']);
            $t->same('convert-single-pdf-model-unpack-failed', $partialInitializer['downstream_convert_single_pdf_model_unpack_boundary']);
            $t->same('ValueError', $partialInitializer['downstream_convert_single_pdf_model_unpack_error_class']);
            $t->same('not enough values to unpack (expected 6, got 5)', $partialInitializer['downstream_convert_single_pdf_model_unpack_error_message']);
            $t->same(false, $partialInitializer['model_list_arity_checked_in_worker_init']);
            $t->same(true, $partialInitializer['model_list_arity_deferred_to_convert_single_pdf']);
            $t->same(true, $partialInitializer['process_single_pdf_catches_downstream_unpack_error']);
            $t->same(false, $partial['executes_python_or_models']);
            $t->same(false, $partial['executes_multiprocessing']);
            $t->same(false, $partial['executes_external_pdf_tools']);

            $overfullHandoff = $overfull['model_handoff'];
            $overfullShareMemory = $overfullHandoff['model_share_memory_review'];
            $overfullInitializer = $overfull['worker_pool']['worker_initializer'];

            $t->same(7, $overfullHandoff['model_slot_count']);
            $t->same(false, $overfullHandoff['model_list_arity_matches_convert_single_pdf_unpack']);
            $t->same('convert-single-pdf-model-unpack-failed', $overfullHandoff['model_list_arity_error_boundary']);
            $t->same('too many values to unpack (expected 6)', $overfullHandoff['model_list_arity_error_message']);
            $t->same([0, 1, 2, 3, 4, 5, 6], $overfullShareMemory['share_memory_model_slot_indexes']);
            $t->same(false, $overfullShareMemory['share_memory_error_found']);
            $t->same(false, $overfullShareMemory['blocks_task_args']);
            $t->same(true, $overfull['worker_pool']['pool_launchable']);
            $t->same('too many values to unpack (expected 6)', $overfullInitializer['downstream_convert_single_pdf_model_unpack_error_message']);
            $t->same(true, $overfullInitializer['process_single_pdf_catches_downstream_unpack_error']);
            $t->same(false, $overfull['executes_python_or_models']);
            $t->same(false, $overfull['executes_multiprocessing']);
            $t->same(false, $overfull['executes_external_pdf_tools']);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
];
