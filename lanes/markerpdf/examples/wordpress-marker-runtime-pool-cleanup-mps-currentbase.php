<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runId = bin2hex(random_bytes(4));
$input = sys_get_temp_dir() . '/markerpdf-runtime-pool-cleanup-mps-input-' . $runId;
$output = sys_get_temp_dir() . '/markerpdf-runtime-pool-cleanup-mps-output-' . $runId;
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
    foreach (['attachment-one.pdf', 'attachment-two.pdf'] as $filename) {
        file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% {$filename}\n%%EOF");
    }

    $batch = new BatchConverter();
    $mps = $batch->runtimeMainPreflightPlan(
        $input,
        $output,
        metadataByFilename: [
            'attachment-one.pdf' => ['title' => 'Attachment One', 'languages' => ['English']],
        ],
        workers: 4,
        torchDevice: 'cpu',
        torchDeviceModel: 'mps'
    );
    $cuda = $batch->runtimeMainPreflightPlan(
        $input,
        $output,
        workers: 4,
        torchDevice: 'cuda',
        torchDeviceModel: 'cpu'
    );
    $blocked = $batch->runtimeMainPreflightPlan(
        $input,
        $output,
        workers: 0,
        torchDevice: 'cpu',
        torchDeviceModel: 'mps'
    );

    $mpsCleanup = $mps['worker_pool']['pool_cleanup'];
    $cudaCleanup = $cuda['worker_pool']['pool_cleanup'];
    $blockedCleanup = $blocked['worker_pool']['pool_cleanup'];

    if (
        $mpsCleanup['cleanup_after_context_exit'] !== true
        || $mpsCleanup['model_list_value_before_delete'] !== 'None'
        || $mpsCleanup['model_list_delete_deletes_none_reference'] !== true
        || $mpsCleanup['worker_model_load_branch_cleanup'] !== true
        || $mpsCleanup['worker_exit_required_for_worker_loaded_models'] !== true
        || $mpsCleanup['parent_model_list_loaded'] !== false
        || $mpsCleanup['worker_init_argument'] !== null
    ) {
        throw new RuntimeException('Expected MPS runtime cleanup preflight to delete the None parent model_lst after relying on worker_exit for worker-loaded models.');
    }
    if (
        $cudaCleanup['model_list_value_before_delete'] !== 'model_lst'
        || $cudaCleanup['parent_share_memory_before_cleanup'] !== true
        || $cudaCleanup['parent_shared_models_deleted_after_context_exit'] !== true
        || $cudaCleanup['worker_model_load_branch_cleanup'] !== false
    ) {
        throw new RuntimeException('Expected CUDA runtime cleanup preflight to delete the parent shared model list after pool context exit.');
    }
    if (
        $blockedCleanup['cleanup_reached'] !== false
        || $blockedCleanup['blocked_by'] !== 'pool-process-count-failed'
        || $blockedCleanup['model_list_value_before_delete'] !== null
    ) {
        throw new RuntimeException('Expected zero-worker runtime cleanup preflight to remain blocked before model_lst deletion.');
    }
    if ($mps['executes_python_or_models'] !== false || $mps['executes_multiprocessing'] !== false || $mps['executes_external_pdf_tools'] !== false) {
        throw new RuntimeException('Runtime pool cleanup smoke must not execute Python models, multiprocessing, or external PDF tools.');
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-pool-cleanup-mps-currentbase',
        'purpose' => 'Review convert.py MPS pool cleanup boundaries for WordPress PDF import queues before Marker model workers are launched.',
        'selected_filenames' => $mps['chunking']['selected_filenames'],
        'mps_model_list_value_before_delete' => $mpsCleanup['model_list_value_before_delete'],
        'mps_model_list_delete_deletes_none_reference' => $mpsCleanup['model_list_delete_deletes_none_reference'],
        'mps_worker_model_load_branch_cleanup' => $mpsCleanup['worker_model_load_branch_cleanup'],
        'mps_worker_exit_required_for_worker_loaded_models' => $mpsCleanup['worker_exit_required_for_worker_loaded_models'],
        'mps_parent_model_list_loaded' => $mpsCleanup['parent_model_list_loaded'],
        'mps_worker_init_argument' => $mpsCleanup['worker_init_argument'],
        'cuda_model_list_value_before_delete' => $cudaCleanup['model_list_value_before_delete'],
        'cuda_parent_share_memory_before_cleanup' => $cudaCleanup['parent_share_memory_before_cleanup'],
        'cuda_parent_shared_models_deleted_after_context_exit' => $cudaCleanup['parent_shared_models_deleted_after_context_exit'],
        'zero_worker_cleanup_blocked_by' => $blockedCleanup['blocked_by'],
        'zero_worker_cleanup_reached' => $blockedCleanup['cleanup_reached'],
        'executes_python_or_models' => $mps['executes_python_or_models'],
        'executes_multiprocessing' => $mps['executes_multiprocessing'],
        'executes_external_pdf_tools' => $mps['executes_external_pdf_tools'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    $removeTree($input);
    $removeTree($output);
}
