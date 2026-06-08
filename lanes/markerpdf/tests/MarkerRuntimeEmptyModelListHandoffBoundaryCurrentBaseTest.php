<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-runtime-empty-model-list-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf empty-model-list runtime folder.');
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
    'records empty load_all_models list handoff before worker conversion' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        try {
            file_put_contents($input . DIRECTORY_SEPARATOR . 'ready.pdf', "%PDF-1.4\n% ready\n%%EOF");

            $plan = (new BatchConverter())->runtimeMainPreflightPlan(
                $input,
                $output,
                workers: 3,
                torchDevice: 'cuda',
                torchDeviceModel: 'cpu',
                modelSlots: []
            );

            $handoff = $plan['model_handoff'];
            $shareMemory = $handoff['model_share_memory_review'];
            $initializer = $plan['worker_pool']['worker_initializer'];

            $t->same('convert.py settings.TORCH_DEVICE model handoff', $handoff['source']);
            $t->same(true, $handoff['model_handoff_reached']);
            $t->same(true, $handoff['model_handoff_success']);
            $t->same(true, $handoff['main_load_all_models']);
            $t->same(true, $handoff['share_memory_before_pool']);
            $t->same(true, $handoff['model_list_empty']);
            $t->same(true, $handoff['worker_init_receives_empty_model_list']);
            $t->same(false, $handoff['worker_loads_models_when_empty_list']);
            $t->same('convert-single-pdf-model-unpack-failed', $handoff['empty_model_list_conversion_error_boundary']);
            $t->same('ValueError', $handoff['empty_model_list_conversion_error_class']);
            $t->contains('expected 6, got 0', $handoff['empty_model_list_conversion_error_message']);
            $t->same(true, $handoff['empty_model_list_caught_by_process_single_pdf']);
            $t->same(false, $handoff['executes_python_or_models']);

            $t->same(true, $shareMemory['review_reached']);
            $t->same(true, $shareMemory['model_list_fixture_used']);
            $t->same(true, $shareMemory['model_list_empty']);
            $t->same(false, $shareMemory['model_list_python_truthy']);
            $t->same('[]', $shareMemory['model_list_value']);
            $t->same(0, $shareMemory['model_slot_count']);
            $t->same([], $shareMemory['model_slots']);
            $t->same([], $shareMemory['share_memory_model_slot_indexes']);
            $t->same([], $shareMemory['none_model_slot_indexes']);
            $t->same(0, $shareMemory['share_memory_call_count']);
            $t->same(false, $shareMemory['share_memory_error_found']);
            $t->same(false, $shareMemory['blocks_conversion_summary']);
            $t->same(false, $shareMemory['blocks_task_args']);
            $t->same(false, $shareMemory['executes_python_or_models']);

            $t->same(true, $initializer['initializer_reached']);
            $t->same('[]', $initializer['shared_model_value']);
            $t->same(false, $initializer['shared_model_is_none']);
            $t->same(true, $initializer['shared_model_is_empty_list']);
            $t->same('shared_model is None', $initializer['worker_init_reload_condition']);
            $t->same(false, $initializer['loads_models_in_worker']);
            $t->same(true, $initializer['parent_shared_model_reused']);
            $t->same(true, $initializer['empty_list_does_not_trigger_worker_load']);
            $t->same('parent-shared-empty-model-list', $initializer['model_refs_source']);
            $t->same('convert-single-pdf-model-unpack-failed', $initializer['downstream_convert_single_pdf_model_unpack_boundary']);
            $t->same(false, $initializer['executes_python_or_models']);
            $t->same(false, $initializer['executes_multiprocessing']);

            $t->same(1, $plan['worker_pool']['task_args_count']);
            $t->same(1, $plan['worker_pool']['total_processes']);
            $t->same(true, $plan['worker_pool']['pool_launchable']);
            $t->same(false, $plan['executes_python_or_models']);
            $t->same(false, $plan['executes_multiprocessing']);
            $t->same(false, $plan['executes_external_pdf_tools']);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
];
