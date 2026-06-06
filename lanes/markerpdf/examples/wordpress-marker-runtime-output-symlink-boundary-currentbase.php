<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runId = bin2hex(random_bytes(4));
$root = sys_get_temp_dir() . '/markerpdf-runtime-output-symlink-smoke-' . $runId;
$input = $root . DIRECTORY_SEPARATOR . 'uploads';

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
    if (!mkdir($input, 0777, true) && !is_dir($input)) {
        throw new RuntimeException('Unable to create markerPDF runtime symlink smoke input.');
    }
    file_put_contents($input . DIRECTORY_SEPARATOR . 'queued.pdf', "%PDF-1.4\n% queued\n%%EOF");

    $realOutput = $root . DIRECTORY_SEPARATOR . 'real-output';
    mkdir($realOutput);
    $directorySymlink = $root . DIRECTORY_SEPARATOR . 'output-dir-link';
    if (!@symlink($realOutput, $directorySymlink)) {
        throw new RuntimeException('Unable to create output directory symlink fixture.');
    }

    $realOutputFile = $root . DIRECTORY_SEPARATOR . 'real-output-file';
    file_put_contents($realOutputFile, 'not a directory');
    $fileSymlink = $root . DIRECTORY_SEPARATOR . 'output-file-link';
    if (!@symlink($realOutputFile, $fileSymlink)) {
        throw new RuntimeException('Unable to create output file symlink fixture.');
    }

    $brokenSymlink = $root . DIRECTORY_SEPARATOR . 'output-broken-link';
    if (!@symlink($root . DIRECTORY_SEPARATOR . 'missing-output-target', $brokenSymlink)) {
        throw new RuntimeException('Unable to create broken output symlink fixture.');
    }

    $batch = new BatchConverter();
    $directoryPlan = $batch->runtimeMainPreflightPlan($input, $directorySymlink, workers: 2);
    $filePlan = $batch->runtimeMainPreflightPlan($input, $fileSymlink, workers: 2);
    $brokenPlan = $batch->runtimeMainPreflightPlan($input, $brokenSymlink, workers: 2);

    $directoryPaths = $directoryPlan['paths'];
    $filePaths = $filePlan['paths'];
    $brokenPaths = $brokenPlan['paths'];

    $result = [
        'scenario' => 'wordpress-marker-runtime-output-symlink-boundary-currentbase',
        'source_truth' => 'sddai/markerPDF convert.py calls os.makedirs(out_folder, exist_ok=True) after os.listdir and before chunking, metadata loading, model handoff, task args, or torch multiprocessing Pool launch',
        'directory_symlink_output_accepted' => $directoryPaths['output_folder_is_symlink'] === true
            && $directoryPaths['output_folder_makedirs_follows_symlink'] === true
            && $directoryPaths['output_folder_creation_blocked'] === false
            && $directoryPlan['worker_pool']['task_args_count'] === 1,
        'directory_symlink_task_out_folder_preserved' => $directoryPlan['worker_pool']['task_args'][0]['out_folder'] === $directorySymlink,
        'file_symlink_output_rejected_before_metadata' => $filePaths['output_folder_is_symlink'] === true
            && $filePaths['output_folder_symlink_target_type'] === 'file'
            && $filePaths['output_folder_creation_error_class'] === 'FileExistsError'
            && $filePlan['metadata']['metadata_load_reached'] === false,
        'broken_symlink_output_rejected_before_chunking' => $brokenPaths['output_folder_is_symlink'] === true
            && $brokenPaths['output_folder_broken_symlink'] === true
            && $brokenPaths['output_path_type'] === 'broken-symlink'
            && $brokenPaths['output_folder_creation_error_class'] === 'FileExistsError'
            && $brokenPlan['chunking']['chunking_reached'] === false,
        'blocked_output_worker_task_args' => [
            'file_symlink' => $filePlan['worker_pool']['task_args_count'],
            'broken_symlink' => $brokenPlan['worker_pool']['task_args_count'],
        ],
        'executes_python_or_models' => $directoryPlan['executes_python_or_models']
            || $filePlan['executes_python_or_models']
            || $brokenPlan['executes_python_or_models'],
        'executes_multiprocessing' => $directoryPlan['executes_multiprocessing']
            || $filePlan['executes_multiprocessing']
            || $brokenPlan['executes_multiprocessing'],
        'executes_external_pdf_tools' => $directoryPlan['executes_external_pdf_tools']
            || $filePlan['executes_external_pdf_tools']
            || $brokenPlan['executes_external_pdf_tools'],
    ];

    if (
        $result['directory_symlink_output_accepted'] !== true
        || $result['directory_symlink_task_out_folder_preserved'] !== true
        || $result['file_symlink_output_rejected_before_metadata'] !== true
        || $result['broken_symlink_output_rejected_before_chunking'] !== true
        || $result['executes_python_or_models'] !== false
        || $result['executes_multiprocessing'] !== false
        || $result['executes_external_pdf_tools'] !== false
    ) {
        throw new RuntimeException('MarkerPDF runtime output symlink boundary smoke failed.');
    }

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    $removeTree($root);
}
