<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-runtime-pool-cleanup-mps-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf runtime pool cleanup folder.');
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
    'records convert.py MPS model_lst None cleanup after pool result drain' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        try {
            foreach (['mps-one.pdf', 'mps-two.pdf'] as $filename) {
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
                torchDevice: 'cpu',
                torchDeviceModel: 'mps'
            );

            $cudaCleanup = $cuda['worker_pool']['pool_cleanup'];
            $t->same('convert.py pool worker_exit and model_lst cleanup boundary', $cudaCleanup['source']);
            $t->same('after_pool_imap_before_del_model_lst', $cudaCleanup['order']);
            $t->same(true, $cudaCleanup['cleanup_reached']);
            $t->same(true, $cudaCleanup['cleanup_after_context_exit']);
            $t->same(true, $cudaCleanup['pool_imap_reached']);
            $t->same(true, $cudaCleanup['model_list_delete_reached']);
            $t->same('del model_lst', $cudaCleanup['model_list_delete_statement']);
            $t->same('model_lst', $cudaCleanup['model_list_value_before_delete']);
            $t->same(false, $cudaCleanup['model_list_delete_deletes_none_reference']);
            $t->same(true, $cudaCleanup['parent_model_list_loaded']);
            $t->same(true, $cudaCleanup['parent_share_memory_before_cleanup']);
            $t->same(true, $cudaCleanup['parent_shared_models_deleted_after_context_exit']);
            $t->same('model_lst', $cudaCleanup['worker_init_argument']);
            $t->same(false, $cudaCleanup['worker_model_load_branch_cleanup']);
            $t->same(false, $cudaCleanup['worker_exit_required_for_worker_loaded_models']);

            $mpsCleanup = $mps['worker_pool']['pool_cleanup'];
            $t->same(true, $mpsCleanup['cleanup_reached']);
            $t->same(true, $mpsCleanup['cleanup_after_context_exit']);
            $t->same(true, $mpsCleanup['pool_imap_completed_required']);
            $t->same(true, $mpsCleanup['pool_imap_reached']);
            $t->same('pool._worker_handler.terminate = worker_exit', $mpsCleanup['worker_handler_terminate_assignment']);
            $t->same(true, $mpsCleanup['worker_handler_terminate_override_reached']);
            $t->same('worker_exit', $mpsCleanup['worker_exit_function']);
            $t->same(true, $mpsCleanup['worker_exit_deletes_global_model_refs']);
            $t->same('del model_refs', $mpsCleanup['worker_exit_delete_statement']);
            $t->same(true, $mpsCleanup['model_list_delete_reached']);
            $t->same('del model_lst', $mpsCleanup['model_list_delete_statement']);
            $t->same('None', $mpsCleanup['model_list_value_before_delete']);
            $t->same(true, $mpsCleanup['model_list_delete_deletes_none_reference']);
            $t->same(false, $mpsCleanup['parent_model_list_loaded']);
            $t->same(false, $mpsCleanup['parent_share_memory_before_cleanup']);
            $t->same(false, $mpsCleanup['parent_shared_models_deleted_after_context_exit']);
            $t->same(null, $mpsCleanup['worker_init_argument']);
            $t->same(true, $mpsCleanup['worker_model_load_branch_cleanup']);
            $t->same(true, $mpsCleanup['worker_exit_required_for_worker_loaded_models']);
            $t->same(false, $mpsCleanup['executes_python_or_models']);
            $t->same(false, $mpsCleanup['executes_multiprocessing']);
            $t->same(false, $mpsCleanup['executes_external_pdf_tools']);

            $mpsHandoff = $mps['model_handoff'];
            $t->same(true, $mpsHandoff['uses_mps_no_shared_memory_branch']);
            $t->same(true, $mpsHandoff['worker_loads_models_when_init_arg_null']);
            $t->same(true, $mps['worker_pool']['worker_initializer']['loads_models_in_worker']);
            $t->same('None', $mps['worker_pool']['worker_initializer']['shared_model_value']);

            $blockedCleanup = $blocked['worker_pool']['pool_cleanup'];
            $t->same(false, $blockedCleanup['cleanup_reached']);
            $t->same('pool-process-count-failed', $blockedCleanup['blocked_by']);
            $t->same(false, $blockedCleanup['pool_imap_reached']);
            $t->same(false, $blockedCleanup['worker_handler_terminate_override_reached']);
            $t->same(false, $blockedCleanup['model_list_delete_reached']);
            $t->same(null, $blockedCleanup['model_list_value_before_delete']);
            $t->same(false, $blockedCleanup['model_list_delete_deletes_none_reference']);
            $t->same(false, $blockedCleanup['cleanup_after_context_exit']);
            $t->same(false, $blockedCleanup['parent_model_list_loaded']);
            $t->same(false, $blockedCleanup['parent_share_memory_before_cleanup']);
            $t->same(false, $blockedCleanup['parent_shared_models_deleted_after_context_exit']);
            $t->same(null, $blockedCleanup['worker_init_argument']);
            $t->same(false, $blockedCleanup['worker_model_load_branch_cleanup']);
            $t->same(false, $blockedCleanup['worker_exit_required_for_worker_loaded_models']);

            $t->same(false, $mps['executes_python_or_models']);
            $t->same(false, $mps['executes_multiprocessing']);
            $t->same(false, $mps['executes_external_pdf_tools']);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
];
