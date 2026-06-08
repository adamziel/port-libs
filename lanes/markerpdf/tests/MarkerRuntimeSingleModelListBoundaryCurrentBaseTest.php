<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\SingleDocumentConverter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-runtime-single-model-list-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf single-model-list folder.');
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
    'records convert_single load_all_models slot order before single pdf conversion' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree): void {
        $output = $makeTempDir();
        try {
            $plan = (new SingleDocumentConverter())->runtimePreflightPlan(
                '/wp/uploads/2026/model-order.pdf',
                $output,
                maxPages: 5,
                startPage: 2,
                languages: '',
                batchMultiplier: 3
            );

            $t->same('markerpdf.convert_single_runtime_preflight.v1', $plan['schema']);
            $t->same(['PYTORCH_ENABLE_MPS_FALLBACK' => '1'], $plan['environment']);
            $t->same(null, $plan['options']['langs']);

            $sequence = $plan['model_load_sequence'];
            $t->same('marker.models.load_all_models', $sequence['source']);
            $t->same('model_lst = load_all_models()', $sequence['upstream_statement']);
            $t->same('after_parse_langs_before_convert_single_pdf', $sequence['order']);
            $t->same('model_lst', $sequence['model_list_variable']);
            $t->same(6, $sequence['model_count']);
            $t->same(null, $sequence['device_argument']);
            $t->same(null, $sequence['dtype_argument']);
            $t->same(false, $sequence['device_dtype_assertion_reached']);
            $t->same(true, $sequence['uses_default_device_and_dtype']);
            $t->same(false, $sequence['native_plan_loads_models']);
            $t->same(false, $sequence['executes_python_or_models']);
            $t->same([
                'setup_detection_model',
                'setup_layout_model',
                'setup_order_model',
                'setup_recognition_model',
                'setup_texify_model',
                'setup_table_rec_model',
            ], $sequence['construction_order']);
            $t->same([
                'texify',
                'layout',
                'order',
                'detection',
                'ocr',
                'table_model',
            ], $sequence['model_slot_order']);
            $t->same([
                [
                    'index' => 0,
                    'label' => 'texify',
                    'setup_function' => 'setup_texify_model',
                    'model_loader' => 'load_texify_model',
                    'processor_loader' => 'load_texify_processor',
                    'checkpoint_source' => 'settings.TEXIFY_MODEL_NAME',
                    'default_device_source' => 'settings.TORCH_DEVICE_MODEL',
                    'default_dtype_source' => 'settings.TEXIFY_DTYPE',
                ],
                [
                    'index' => 1,
                    'label' => 'layout',
                    'setup_function' => 'setup_layout_model',
                    'model_loader' => 'load_detection_model',
                    'processor_loader' => 'load_detection_processor',
                    'checkpoint_source' => 'settings.LAYOUT_MODEL_CHECKPOINT',
                    'default_device_source' => null,
                    'default_dtype_source' => null,
                ],
                [
                    'index' => 2,
                    'label' => 'order',
                    'setup_function' => 'setup_order_model',
                    'model_loader' => 'load_order_model',
                    'processor_loader' => 'load_order_processor',
                    'checkpoint_source' => null,
                    'default_device_source' => null,
                    'default_dtype_source' => null,
                ],
                [
                    'index' => 3,
                    'label' => 'detection',
                    'setup_function' => 'setup_detection_model',
                    'model_loader' => 'load_detection_model',
                    'processor_loader' => 'load_detection_processor',
                    'checkpoint_source' => null,
                    'default_device_source' => null,
                    'default_dtype_source' => null,
                ],
                [
                    'index' => 4,
                    'label' => 'ocr',
                    'setup_function' => 'setup_recognition_model',
                    'model_loader' => 'load_recognition_model',
                    'processor_loader' => 'load_recognition_processor',
                    'checkpoint_source' => null,
                    'default_device_source' => null,
                    'default_dtype_source' => null,
                ],
                [
                    'index' => 5,
                    'label' => 'table_model',
                    'setup_function' => 'setup_table_rec_model',
                    'model_loader' => 'load_table_model',
                    'processor_loader' => 'load_table_processor',
                    'checkpoint_source' => null,
                    'default_device_source' => null,
                    'default_dtype_source' => null,
                ],
            ], $sequence['model_slots']);

            $boundary = $plan['model_boundary'];
            $t->same('load_all_models', $boundary['load_function']);
            $t->same('marker.models.load_all_models', $boundary['load_function_source']);
            $t->same('load_all_models()', $boundary['model_list_source']);
            $t->same('model_lst', $boundary['model_list_variable']);
            $t->same(6, $boundary['model_count']);
            $t->same($sequence['model_slot_order'], $boundary['model_slot_order']);
            $t->same(true, $boundary['recognition_model_always_loaded_for_single_document']);
            $t->same(false, $boundary['single_document_share_memory_loop']);
            $t->same(false, $boundary['native_plan_loads_models']);
            $t->same(true, $boundary['upstream_model_execution_required']);

            $t->same(true, $plan['conversion_call']['receives_model_list']);
            $t->same('model_lst returned by load_all_models()', $plan['conversion_call']['model_argument_source']);
            $t->same($sequence['model_slot_order'], $plan['conversion_call']['model_slot_order']);
            $t->same(false, $plan['executes_python_or_models']);
            $t->same(false, $plan['executes_multiprocessing']);
            $t->same(false, $plan['executes_external_pdf_tools']);
        } finally {
            $removeTree($output);
        }
    },
];
