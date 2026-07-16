<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runId = bin2hex(random_bytes(4));
$root = sys_get_temp_dir() . '/markerpdf-runtime-output-permission-smoke-' . $runId;
$input = $root . DIRECTORY_SEPARATOR . 'uploads';
$lockedParent = $root . DIRECTORY_SEPARATOR . 'locked-output-parent';

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
    mkdir($input, 0777, true);
    foreach (['ready.pdf', 'wp-upload-sidecar.txt'] as $filename) {
        file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% {$filename}\n%%EOF");
    }

    mkdir($lockedParent);
    chmod($lockedParent, 0500);
    $output = $lockedParent . DIRECTORY_SEPARATOR . 'marker-output';
    $metadataFile = $root . DIRECTORY_SEPARATOR . 'metadata.json';
    file_put_contents($metadataFile, json_encode([
        'ready.pdf' => ['title' => 'Ready WordPress Import'],
    ], JSON_THROW_ON_ERROR));

    $plan = (new BatchConverter())->runtimeMainPreflightPlan(
        $input,
        $output,
        metadataFile: $metadataFile,
        workers: 4,
        torchDevice: 'cuda',
        torchDeviceModel: 'cpu'
    );

    $paths = $plan['paths'];
    $listedFiles = $plan['input_listing']['file_basenames'];
    sort($listedFiles, SORT_STRING);

    $result = [
        'scenario' => 'wordpress-marker-runtime-output-permission-currentbase',
        'source_truth' => 'sddai/markerPDF convert.py calls os.makedirs(out_folder, exist_ok=True) after os.listdir and before chunking, metadata loading, model handoff, task args, or torch multiprocessing Pool launch.',
        'input_listing_reached_before_output_failure' => $listedFiles === ['ready.pdf', 'wp-upload-sidecar.txt'],
        'output_creation_permission_error' => $paths['output_folder_creation_error_boundary'] === 'output-folder-parent-permission-denied'
            && $paths['output_folder_creation_error_class'] === 'PermissionError'
            && $paths['output_folder_parent_permission_blocked'] === true
            && $paths['output_folder_creation_parent_writable'] === false,
        'blocked_before_chunking' => $plan['chunking']['chunking_reached'] === false
            && $plan['chunking']['chunk_error_boundary'] === 'output-folder-create-failed',
        'blocked_before_metadata_load' => $plan['metadata']['metadata_file'] === $metadataFile
            && $plan['metadata']['metadata_load_reached'] === false,
        'blocked_before_model_handoff' => $plan['model_handoff']['model_handoff_reached'] === false
            && $plan['model_handoff']['upstream_model_execution_required'] === false,
        'blocked_before_worker_pool' => $plan['worker_pool']['task_args_count'] === 0
            && $plan['worker_pool']['pool_launchable'] === false
            && $plan['worker_pool']['pool_error_boundary'] === 'output-folder-create-failed',
        'output_folder_created_by_native_plan' => is_dir($output),
        'executes_python_or_models' => $plan['executes_python_or_models'],
        'executes_multiprocessing' => $plan['executes_multiprocessing'],
        'executes_external_pdf_tools' => $plan['executes_external_pdf_tools'],
    ];

    if (
        $result['input_listing_reached_before_output_failure'] !== true
        || $result['output_creation_permission_error'] !== true
        || $result['blocked_before_chunking'] !== true
        || $result['blocked_before_metadata_load'] !== true
        || $result['blocked_before_model_handoff'] !== true
        || $result['blocked_before_worker_pool'] !== true
        || $result['output_folder_created_by_native_plan'] !== false
        || $result['executes_python_or_models'] !== false
        || $result['executes_multiprocessing'] !== false
        || $result['executes_external_pdf_tools'] !== false
    ) {
        throw new RuntimeException('MarkerPDF runtime output permission smoke failed.');
    }

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    if (is_dir($lockedParent)) {
        chmod($lockedParent, 0700);
    }
    $removeTree($root);
}
