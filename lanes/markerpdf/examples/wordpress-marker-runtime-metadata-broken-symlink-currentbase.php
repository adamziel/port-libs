<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runId = bin2hex(random_bytes(4));
$root = sys_get_temp_dir() . '/markerpdf-runtime-metadata-broken-symlink-smoke-' . $runId;
$input = $root . DIRECTORY_SEPARATOR . 'uploads';
$output = $root . DIRECTORY_SEPARATOR . 'marker-output';

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
    mkdir($output, 0777, true);
    foreach (['queued.pdf', 'review-sidecar.txt'] as $filename) {
        file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% {$filename}\n%%EOF");
    }

    $metadataLink = $root . DIRECTORY_SEPARATOR . 'metadata-broken-link.json';
    $missingTarget = $root . DIRECTORY_SEPARATOR . 'missing-wordpress-metadata.json';
    if (!@symlink($missingTarget, $metadataLink)) {
        throw new RuntimeException('Unable to create broken metadata-file symlink fixture.');
    }

    $plan = (new BatchConverter())->runtimeMainPreflightPlan(
        $input,
        $output,
        workers: 4,
        metadataFile: $metadataLink,
        torchDevice: 'cuda',
        torchDeviceModel: 'cpu'
    );

    if (
        $plan['metadata']['metadata_file_path_type'] !== 'broken-symlink'
        || $plan['metadata']['metadata_file_broken_symlink'] !== true
        || $plan['metadata']['metadata_file_open_broken_symlink_fails'] !== true
        || $plan['metadata']['metadata_error_class'] !== 'FileNotFoundError'
        || $plan['metadata']['metadata_load_success'] !== false
        || $plan['spawn_start_method']['start_method_reached'] !== false
        || $plan['model_handoff']['model_handoff_reached'] !== false
        || $plan['worker_pool']['task_args_count'] !== 0
    ) {
        throw new RuntimeException('Expected broken metadata_file symlink to fail at open() before spawn, model handoff, task args, or pool launch.');
    }
    if (
        $plan['executes_python_or_models'] !== false
        || $plan['executes_multiprocessing'] !== false
        || $plan['executes_external_pdf_tools'] !== false
    ) {
        throw new RuntimeException('Metadata broken-symlink smoke must not execute Python, models, multiprocessing, or external PDF tools.');
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-metadata-broken-symlink-currentbase',
        'purpose' => 'Review convert.py metadata_file broken symlink open() semantics for WordPress batch imports before Marker model handoff or task construction.',
        'source' => 'sddai/markerPDF convert.py::main metadata_file = os.path.abspath(args.metadata_file); open(metadata_file, "r"); json.load(f)',
        'selected_filenames' => $plan['chunking']['selected_filenames'],
        'metadata_file_path_type' => $plan['metadata']['metadata_file_path_type'],
        'metadata_file_is_symlink' => $plan['metadata']['metadata_file_is_symlink'],
        'metadata_file_symlink_target_exists' => $plan['metadata']['metadata_file_symlink_target_exists'],
        'metadata_file_broken_symlink' => $plan['metadata']['metadata_file_broken_symlink'],
        'metadata_file_open_broken_symlink_fails' => $plan['metadata']['metadata_file_open_broken_symlink_fails'],
        'metadata_error_boundary' => $plan['metadata']['metadata_error_boundary'],
        'metadata_error_class' => $plan['metadata']['metadata_error_class'],
        'blocks_spawn' => $plan['spawn_start_method']['start_method_reached'] === false,
        'blocks_model_handoff' => $plan['model_handoff']['model_handoff_reached'] === false,
        'task_args_count' => $plan['worker_pool']['task_args_count'],
        'executes_python_or_models' => false,
        'executes_multiprocessing' => false,
        'executes_external_pdf_tools' => false,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($root);
}
