<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;
use PortLibs\MarkerPDF\MarkerRuntimePlanner;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-runtime-mps-case-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf MPS case boundary folder.');
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
    'preserves convert.py exact lowercase mps branch for runtime model handoff' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        try {
            file_put_contents($input . DIRECTORY_SEPARATOR . 'uppercase-mps.pdf', "%PDF-1.4\n% uppercase mps\n%%EOF");

            $batch = new BatchConverter();
            $uppercaseDevice = $batch->runtimeMainPreflightPlan(
                $input,
                $output,
                workers: 3,
                torchDevice: 'MPS',
                torchDeviceModel: 'cpu',
                modelSlots: ['layout-detector']
            );
            $uppercaseModel = $batch->runtimeMainPreflightPlan(
                $input,
                $output,
                workers: 3,
                torchDevice: 'cpu',
                torchDeviceModel: 'MPS',
                modelSlots: ['layout-detector']
            );
            $spacedDevice = $batch->runtimeMainPreflightPlan(
                $input,
                $output,
                workers: 3,
                torchDevice: ' mps ',
                torchDeviceModel: 'cpu',
                modelSlots: ['layout-detector']
            );
            $lowercase = $batch->runtimeMainPreflightPlan(
                $input,
                $output,
                workers: 3,
                torchDevice: 'mps',
                torchDeviceModel: 'cpu',
                modelSlots: ['layout-detector']
            );

            foreach ([$uppercaseDevice, $uppercaseModel, $spacedDevice] as $plan) {
                $handoff = $plan['model_handoff'];
                $shareMemory = $handoff['model_share_memory_review'];
                $initializer = $plan['worker_pool']['worker_initializer'];

                $t->same(true, $handoff['model_handoff_reached']);
                $t->same(false, $handoff['uses_mps_no_shared_memory_branch']);
                $t->same(true, $handoff['main_load_all_models']);
                $t->same(true, $handoff['share_memory_before_pool']);
                $t->same('model_lst', $handoff['worker_init_argument']);
                $t->same(false, $handoff['worker_loads_models_when_init_arg_null']);
                $t->same(null, $handoff['warning']);
                $t->same(true, $shareMemory['review_reached']);
                $t->same('model_lst', $shareMemory['model_list_value']);
                $t->same([0], $shareMemory['share_memory_model_slot_indexes']);
                $t->same(1, $shareMemory['share_memory_call_count']);
                $t->same('model_lst', $initializer['shared_model_value']);
                $t->same(false, $initializer['loads_models_in_worker']);
                $t->same(true, $initializer['parent_shared_model_reused']);
                $t->same(false, $plan['executes_python_or_models']);
                $t->same(false, $plan['executes_multiprocessing']);
            }

            $lowercaseHandoff = $lowercase['model_handoff'];
            $t->same(true, $lowercaseHandoff['uses_mps_no_shared_memory_branch']);
            $t->same(false, $lowercaseHandoff['main_load_all_models']);
            $t->same(false, $lowercaseHandoff['share_memory_before_pool']);
            $t->same(null, $lowercaseHandoff['worker_init_argument']);
            $t->same(true, $lowercaseHandoff['worker_loads_models_when_init_arg_null']);

            $planner = new MarkerRuntimePlanner();
            $standaloneUppercase = $planner->convertPyMultiprocessingPlan(
                [
                    [
                        'filepath' => $input . DIRECTORY_SEPARATOR . 'uppercase-mps.pdf',
                        'out_folder' => $output,
                        'metadata' => null,
                        'min_length' => null,
                    ],
                ],
                workers: 3,
                torchDevice: 'MPS',
                torchDeviceModel: 'CPU'
            );
            $standaloneSpaced = $planner->convertPyMultiprocessingPlan(
                [
                    [
                        'filepath' => $input . DIRECTORY_SEPARATOR . 'uppercase-mps.pdf',
                        'out_folder' => $output,
                        'metadata' => null,
                        'min_length' => null,
                    ],
                ],
                workers: 3,
                torchDevice: ' mps ',
                torchDeviceModel: 'CPU'
            );
            $standaloneLowercase = $planner->convertPyMultiprocessingPlan(
                [
                    [
                        'filepath' => $input . DIRECTORY_SEPARATOR . 'uppercase-mps.pdf',
                        'out_folder' => $output,
                        'metadata' => null,
                        'min_length' => null,
                    ],
                ],
                workers: 3,
                torchDevice: 'mps',
                torchDeviceModel: 'CPU'
            );

            foreach ([$standaloneUppercase, $standaloneSpaced] as $standalonePlan) {
                $t->same(true, $standalonePlan['model_handoff']['main_load_all_models']);
                $t->same(true, $standalonePlan['model_handoff']['share_memory_before_pool']);
                $t->same('shared_model_list', $standalonePlan['model_handoff']['worker_init_argument']);
                $t->same(false, $standalonePlan['model_handoff']['worker_loads_models_when_init_arg_null']);
                $t->same(false, $standalonePlan['model_handoff']['mps_disables_shared_model_list']);
                $t->same(null, $standalonePlan['model_handoff']['warning']);
                $t->same(false, $standalonePlan['executes_python_or_models']);
                $t->same(false, $standalonePlan['executes_multiprocessing']);
            }

            $t->same(false, $standaloneLowercase['model_handoff']['main_load_all_models']);
            $t->same(false, $standaloneLowercase['model_handoff']['share_memory_before_pool']);
            $t->same(null, $standaloneLowercase['model_handoff']['worker_init_argument']);
            $t->same(true, $standaloneLowercase['model_handoff']['worker_loads_models_when_init_arg_null']);
            $t->same(true, $standaloneLowercase['model_handoff']['mps_disables_shared_model_list']);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
];
