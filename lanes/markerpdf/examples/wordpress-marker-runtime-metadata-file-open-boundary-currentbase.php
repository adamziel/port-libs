<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runId = bin2hex(random_bytes(4));
$root = sys_get_temp_dir() . '/markerpdf-runtime-metadata-open-smoke-' . $runId;
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
    foreach (['queued.pdf', 'missing-meta.pdf'] as $filename) {
        file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% {$filename}\n%%EOF");
    }

    $metadataJson = $root . DIRECTORY_SEPARATOR . 'metadata-source.json';
    file_put_contents($metadataJson, json_encode([
        'queued.pdf' => ['title' => 'Queued WordPress Metadata'],
    ], JSON_THROW_ON_ERROR));
    $metadataLink = $root . DIRECTORY_SEPARATOR . 'metadata-link.json';
    if (!@symlink($metadataJson, $metadataLink)) {
        throw new RuntimeException('Unable to create metadata-file symlink fixture.');
    }
    $metadataDirectory = $root . DIRECTORY_SEPARATOR . 'metadata-directory.json';
    mkdir($metadataDirectory);

    $batch = new BatchConverter();
    $symlinkPlan = $batch->runtimeMainPreflightPlan(
        $input,
        $output,
        workers: 4,
        metadataFile: $metadataLink,
        torchDevice: 'cuda',
        torchDeviceModel: 'cpu'
    );
    $directoryPlan = $batch->runtimeMainPreflightPlan(
        $input,
        $output,
        workers: 4,
        metadataFile: $metadataDirectory,
        torchDevice: 'cuda',
        torchDeviceModel: 'cpu'
    );

    $taskArgsByName = [];
    foreach ($symlinkPlan['worker_pool']['task_args'] as $taskArg) {
        $taskArgsByName[basename($taskArg['filepath'])] = $taskArg;
    }

    if (
        $symlinkPlan['metadata']['metadata_file_is_symlink'] !== true
        || $symlinkPlan['metadata']['metadata_file_open_follows_symlink'] !== true
        || $symlinkPlan['metadata']['metadata_load_success'] !== true
        || $taskArgsByName['queued.pdf']['metadata'] !== ['title' => 'Queued WordPress Metadata']
    ) {
        throw new RuntimeException('Expected metadata-file symlink to load JSON through upstream open() semantics.');
    }
    if (
        $directoryPlan['metadata']['metadata_file_path_type'] !== 'directory'
        || $directoryPlan['metadata']['metadata_error_class'] !== 'IsADirectoryError'
        || $directoryPlan['worker_pool']['task_args_count'] !== 0
        || $directoryPlan['model_handoff']['model_handoff_reached'] !== false
    ) {
        throw new RuntimeException('Expected directory metadata_file to fail at open() before model handoff or task args.');
    }
    if (
        $symlinkPlan['executes_python_or_models'] !== false
        || $symlinkPlan['executes_multiprocessing'] !== false
        || $directoryPlan['executes_python_or_models'] !== false
        || $directoryPlan['executes_external_pdf_tools'] !== false
    ) {
        throw new RuntimeException('Metadata-file boundary smoke must not execute Python, models, multiprocessing, or external PDF tools.');
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-metadata-file-open-boundary-currentbase',
        'purpose' => 'Review convert.py metadata_file open() path semantics for WordPress batch imports before Marker model handoff or task construction.',
        'source' => 'sddai/markerPDF convert.py::main metadata_file = os.path.abspath(args.metadata_file); open(metadata_file, "r"); json.load(f)',
        'selected_filenames' => $symlinkPlan['chunking']['selected_filenames'],
        'symlink_metadata_file_path_type' => $symlinkPlan['metadata']['metadata_file_path_type'],
        'symlink_metadata_file_is_symlink' => $symlinkPlan['metadata']['metadata_file_is_symlink'],
        'symlink_metadata_open_follows_symlink' => $symlinkPlan['metadata']['metadata_file_open_follows_symlink'],
        'symlink_metadata_loaded_filenames' => $symlinkPlan['metadata']['metadata_filenames'],
        'symlink_task_metadata_title' => $taskArgsByName['queued.pdf']['metadata']['title'],
        'directory_metadata_file_path_type' => $directoryPlan['metadata']['metadata_file_path_type'],
        'directory_metadata_error_boundary' => $directoryPlan['metadata']['metadata_error_boundary'],
        'directory_metadata_error_class' => $directoryPlan['metadata']['metadata_error_class'],
        'directory_metadata_blocks_spawn' => $directoryPlan['spawn_start_method']['start_method_reached'] === false,
        'directory_metadata_blocks_model_handoff' => $directoryPlan['model_handoff']['model_handoff_reached'] === false,
        'directory_metadata_task_args_count' => $directoryPlan['worker_pool']['task_args_count'],
        'executes_python_or_models' => false,
        'executes_multiprocessing' => false,
        'executes_external_pdf_tools' => false,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($root);
}
