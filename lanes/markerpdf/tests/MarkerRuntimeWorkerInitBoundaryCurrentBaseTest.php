<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-runtime-worker-init-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf worker-init boundary folder.');
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
    'records convert.py worker_init shared-model branch before process_single_pdf' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        try {
            foreach (['chapter-one.pdf', 'chapter-two.pdf'] as $filename) {
                file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% {$filename}\n%%EOF");
            }

            $batch = new BatchConverter();
            $cuda = $batch->runtimeMainPreflightPlan(
                $input,
                $output,
                workers: 4,
                torchDevice: 'cuda',
                torchDeviceModel: 'cpu'
            );
            $mps = $batch->runtimeMainPreflightPlan(
                $input,
                $output,
                workers: 4,
                torchDevice: 'cpu',
                torchDeviceModel: 'mps'
            );
            $blocked = $batch->runtimeMainPreflightPlan(
                $input,
                $output,
                workers: 0,
                torchDevice: 'cuda',
                torchDeviceModel: 'cpu'
            );

            $initializer = $cuda['worker_pool']['worker_initializer'];
            $t->same('convert.py worker_init shared_model boundary', $initializer['source']);
            $t->same('after_pool_enter_before_process_single_pdf', $initializer['order']);
            $t->same(true, $initializer['initializer_reached']);
            $t->same(null, $initializer['blocked_by']);
            $t->same('worker_init', $initializer['initializer']);
            $t->same('shared_model', $initializer['initializer_argument_name']);
            $t->same('initargs=(model_lst,)', $initializer['pool_initargs_source']);
            $t->same(2, $initializer['processes']);
            $t->same('model_lst', $initializer['shared_model_value']);
            $t->same(false, $initializer['shared_model_is_none']);
            $t->same(false, $initializer['loads_models_in_worker']);
            $t->same(true, $initializer['parent_shared_model_reused']);
            $t->same(null, $initializer['load_all_models_call']);
            $t->same('model_refs', $initializer['worker_global_variable']);
            $t->same('model_refs = shared_model', $initializer['model_refs_assignment']);
            $t->same('parent-shared-model-list', $initializer['model_refs_source']);
            $t->same(true, $initializer['process_single_pdf_after_initializer']);
            $t->same(false, $initializer['upstream_worker_model_execution_required']);
            $t->same(false, $initializer['executes_python_or_models']);
            $t->same(false, $initializer['executes_multiprocessing']);

            $mpsInitializer = $mps['worker_pool']['worker_initializer'];
            $t->same(true, $mpsInitializer['initializer_reached']);
            $t->same('None', $mpsInitializer['shared_model_value']);
            $t->same(true, $mpsInitializer['shared_model_is_none']);
            $t->same(true, $mpsInitializer['loads_models_in_worker']);
            $t->same(false, $mpsInitializer['parent_shared_model_reused']);
            $t->same('load_all_models()', $mpsInitializer['load_all_models_call']);
            $t->same('worker-loaded-model-list', $mpsInitializer['model_refs_source']);
            $t->same(true, $mpsInitializer['upstream_worker_model_execution_required']);
            $t->same(false, $mpsInitializer['executes_python_or_models']);

            $blockedInitializer = $blocked['worker_pool']['worker_initializer'];
            $t->same(false, $blockedInitializer['initializer_reached']);
            $t->same('pool-process-count-failed', $blockedInitializer['blocked_by']);
            $t->same(0, $blockedInitializer['processes']);
            $t->same(null, $blockedInitializer['shared_model_value']);
            $t->same(false, $blockedInitializer['loads_models_in_worker']);
            $t->same(false, $blockedInitializer['parent_shared_model_reused']);
            $t->same(false, $blockedInitializer['process_single_pdf_after_initializer']);
            $t->same(false, $blockedInitializer['executes_multiprocessing']);
            $t->same(false, $blocked['executes_python_or_models']);
            $t->same(false, $blocked['executes_external_pdf_tools']);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
];
