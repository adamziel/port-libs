<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runId = bin2hex(random_bytes(4));
$root = sys_get_temp_dir() . '/markerpdf-runtime-input-symlink-smoke-' . $runId;
$output = sys_get_temp_dir() . '/markerpdf-runtime-input-symlink-output-' . $runId;

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
    $realInput = $root . DIRECTORY_SEPARATOR . 'real-uploads';
    $inputLink = $root . DIRECTORY_SEPARATOR . 'uploads-link';
    if (!mkdir($realInput, 0777, true) && !is_dir($realInput)) {
        throw new RuntimeException('Unable to create markerPDF input symlink smoke source.');
    }
    if (!mkdir($output, 0777, true) && !is_dir($output)) {
        throw new RuntimeException('Unable to create markerPDF input symlink smoke output.');
    }

    file_put_contents($realInput . DIRECTORY_SEPARATOR . 'wp-symlinked-report.pdf', "%PDF-1.4\n% symlinked report\n%%EOF");
    file_put_contents($realInput . DIRECTORY_SEPARATOR . 'wp-symlinked-sidecar.txt', 'WordPress upload sidecar');
    if (!@symlink($realInput, $inputLink)) {
        throw new RuntimeException('Unable to create markerPDF input directory symlink fixture.');
    }

    $plan = (new BatchConverter())->runtimeMainPreflightPlan(
        $inputLink,
        $output,
        workers: 3,
        metadataByFilename: [
            'wp-symlinked-report.pdf' => ['title' => 'Symlinked WordPress Upload'],
        ]
    );
    $resolution = $plan['paths']['path_resolution'];
    $taskArgs = $plan['worker_pool']['task_args'];

    $result = [
        'scenario' => 'wordpress-marker-runtime-input-symlink-boundary-currentbase',
        'source_truth' => 'sddai/markerPDF convert.py uses os.path.abspath(args.in_folder), os.listdir(in_folder), os.path.isfile(os.path.join(in_folder, f)), and task_args filepaths built from that abspath string before metadata handoff or model workers.',
        'input_symlink_listdir_followed' => $resolution['input_folder_is_symlink'] === true
            && $resolution['input_folder_listdir_follows_symlink'] === true
            && $resolution['input_folder_symlink_target_type'] === 'directory',
        'abspath_preserved_symlink_path' => $resolution['absolute_input_folder'] === $inputLink
            && $resolution['input_folder_abspath_does_not_resolve_symlink'] === true
            && $resolution['input_folder_realpath'] === $realInput,
        'task_paths_preserve_symlink_prefix' => isset($taskArgs[0], $taskArgs[1])
            && str_starts_with((string) $taskArgs[0]['filepath'], $inputLink . DIRECTORY_SEPARATOR)
            && str_starts_with((string) $taskArgs[1]['filepath'], $inputLink . DIRECTORY_SEPARATOR),
        'sidecar_remains_task_candidate_before_worker_preflight' => in_array(
            'wp-symlinked-sidecar.txt',
            $plan['input_listing']['selected_non_pdf_filenames'],
            true
        ),
        'metadata_lookup_uses_entry_basename' => ($plan['worker_pool']['task_arg_identity_review']['metadata_lookup_uses_entry_basename'] ?? null) === true,
        'executes_python_or_models' => $plan['executes_python_or_models'],
        'executes_multiprocessing' => $plan['executes_multiprocessing'],
        'executes_external_pdf_tools' => $plan['executes_external_pdf_tools'],
    ];

    if (
        $result['input_symlink_listdir_followed'] !== true
        || $result['abspath_preserved_symlink_path'] !== true
        || $result['task_paths_preserve_symlink_prefix'] !== true
        || $result['sidecar_remains_task_candidate_before_worker_preflight'] !== true
        || $result['metadata_lookup_uses_entry_basename'] !== true
        || $result['executes_python_or_models'] !== false
        || $result['executes_multiprocessing'] !== false
        || $result['executes_external_pdf_tools'] !== false
    ) {
        throw new RuntimeException('MarkerPDF runtime input symlink boundary smoke failed.');
    }

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    $removeTree($root);
    $removeTree($output);
}
