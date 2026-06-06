<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runId = bin2hex(random_bytes(4));
$input = sys_get_temp_dir() . '/markerpdf-runtime-worker-init-input-' . $runId;
$output = sys_get_temp_dir() . '/markerpdf-runtime-worker-init-output-' . $runId;
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
    foreach (['chapter-one.pdf', 'chapter-two.pdf'] as $filename) {
        file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% {$filename}\n%%EOF");
    }

    $batch = new BatchConverter();
    $sharedPlan = $batch->runtimeMainPreflightPlan(
        $input,
        $output,
        metadataByFilename: [
            'chapter-one.pdf' => ['title' => 'Chapter One', 'languages' => ['English']],
        ],
        workers: 4,
        torchDevice: 'cuda',
        torchDeviceModel: 'cpu'
    );
    $mpsPlan = $batch->runtimeMainPreflightPlan(
        $input,
        $output,
        workers: 4,
        torchDevice: 'mps',
        torchDeviceModel: 'cpu'
    );
    $blockedPlan = $batch->runtimeMainPreflightPlan(
        $input,
        $output,
        workers: 0,
        torchDevice: 'cuda',
        torchDeviceModel: 'cpu'
    );

    $sharedInitializer = $sharedPlan['worker_pool']['worker_initializer'];
    $mpsInitializer = $mpsPlan['worker_pool']['worker_initializer'];
    $blockedInitializer = $blockedPlan['worker_pool']['worker_initializer'];

    if (
        $sharedInitializer['parent_shared_model_reused'] !== true
        || $sharedInitializer['loads_models_in_worker'] !== false
        || $sharedInitializer['model_refs_source'] !== 'parent-shared-model-list'
    ) {
        throw new RuntimeException('Expected CUDA/CPU runtime worker_init to reuse the parent shared model list.');
    }
    if (
        $mpsInitializer['shared_model_is_none'] !== true
        || $mpsInitializer['loads_models_in_worker'] !== true
        || $mpsInitializer['model_refs_source'] !== 'worker-loaded-model-list'
    ) {
        throw new RuntimeException('Expected MPS runtime worker_init to load models inside each worker.');
    }
    if (
        $blockedInitializer['initializer_reached'] !== false
        || $blockedInitializer['blocked_by'] !== 'pool-process-count-failed'
        || $blockedInitializer['process_single_pdf_after_initializer'] !== false
    ) {
        throw new RuntimeException('Expected invalid worker count to block worker_init before process_single_pdf.');
    }
    if (
        $sharedPlan['executes_python_or_models'] !== false
        || $mpsPlan['executes_python_or_models'] !== false
        || $sharedPlan['executes_multiprocessing'] !== false
        || $sharedPlan['executes_external_pdf_tools'] !== false
    ) {
        throw new RuntimeException('Runtime worker-init smoke must not execute Python, models, multiprocessing, or external PDF tools.');
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-worker-init-boundary-currentbase',
        'purpose' => 'Review convert.py worker_init shared_model boundaries for a WordPress PDF batch import queue without launching Marker model workers.',
        'selected_filenames' => $sharedPlan['chunking']['selected_filenames'],
        'shared_initializer_reached' => $sharedInitializer['initializer_reached'],
        'shared_model_value' => $sharedInitializer['shared_model_value'],
        'shared_parent_model_reused' => $sharedInitializer['parent_shared_model_reused'],
        'shared_loads_models_in_worker' => $sharedInitializer['loads_models_in_worker'],
        'mps_shared_model_value' => $mpsInitializer['shared_model_value'],
        'mps_loads_models_in_worker' => $mpsInitializer['loads_models_in_worker'],
        'mps_model_refs_source' => $mpsInitializer['model_refs_source'],
        'zero_worker_initializer_reached' => $blockedInitializer['initializer_reached'],
        'zero_worker_initializer_blocked_by' => $blockedInitializer['blocked_by'],
        'executes_python_or_models' => $sharedPlan['executes_python_or_models'],
        'executes_multiprocessing' => $sharedPlan['executes_multiprocessing'],
        'executes_external_pdf_tools' => $sharedPlan['executes_external_pdf_tools'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    $removeTree($input);
    $removeTree($output);
}
