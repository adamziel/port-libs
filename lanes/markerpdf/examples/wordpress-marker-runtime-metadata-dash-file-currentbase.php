<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runId = bin2hex(random_bytes(4));
$root = sys_get_temp_dir() . '/markerpdf-runtime-metadata-dash-smoke-' . $runId;
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

    foreach (['dash.pdf', 'other.pdf'] as $filename) {
        file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% {$filename}\n%%EOF");
    }
    mkdir($input . DIRECTORY_SEPARATOR . '-');
    file_put_contents($output . DIRECTORY_SEPARATOR . '-', '{"other.pdf": {"title": "Output Decoy",}');

    $dashMetadata = $root . DIRECTORY_SEPARATOR . '-';
    file_put_contents($dashMetadata, json_encode([
        'dash.pdf' => ['title' => 'Dash Metadata Import', 'languages' => ['English']],
    ], JSON_THROW_ON_ERROR));

    $previousCwd = getcwd();
    if (!is_string($previousCwd)) {
        throw new RuntimeException('Unable to capture current working directory for dash metadata smoke.');
    }

    try {
        if (!chdir($root)) {
            throw new RuntimeException('Unable to enter dash metadata smoke root.');
        }

        $batch = new BatchConverter();
        $argumentPlan = $batch->runtimeMainArgumentPreflightPlan([
            $input,
            $output,
            '--metadata_file',
            '-',
        ]);
        $runtimePlan = $batch->runtimeMainPreflightPlan(
            $input,
            $output,
            workers: 4,
            metadataFile: '-',
            torchDevice: 'cuda',
            torchDeviceModel: 'cpu'
        );
    } finally {
        chdir($previousCwd);
    }

    $taskArgsByName = [];
    foreach ($runtimePlan['worker_pool']['task_args'] as $taskArg) {
        $taskArgsByName[basename($taskArg['filepath'])] = $taskArg;
    }

    if (
        $argumentPlan['parse_args']['parse_args_success'] !== true
        || $argumentPlan['arguments']['options']['metadata_file'] !== '-'
        || $runtimePlan['metadata']['metadata_file_is_dash_literal'] !== true
        || $runtimePlan['metadata']['metadata_file_dash_treated_as_stdin'] !== false
        || $runtimePlan['metadata']['metadata_file_stdin_read'] !== false
        || $runtimePlan['metadata']['metadata_file'] !== $dashMetadata
        || $runtimePlan['metadata']['metadata_load_success'] !== true
        || $taskArgsByName['dash.pdf']['metadata']['title'] !== 'Dash Metadata Import'
    ) {
        throw new RuntimeException('Expected --metadata_file - to open a literal process-cwd file, not stdin or input/output decoys.');
    }
    if (
        $runtimePlan['executes_python_or_models'] !== false
        || $runtimePlan['executes_multiprocessing'] !== false
        || $runtimePlan['executes_external_pdf_tools'] !== false
    ) {
        throw new RuntimeException('Dash metadata-file boundary smoke must not execute Python, models, multiprocessing, or external PDF tools.');
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-metadata-dash-file-currentbase',
        'purpose' => 'Review convert.py --metadata_file - path handling for WordPress batch imports before Marker model handoff or task construction.',
        'source' => 'sddai/markerPDF convert.py::main argparse + os.path.abspath(args.metadata_file) + open(metadata_file, "r")',
        'metadata_arg_value' => $argumentPlan['arguments']['options']['metadata_file'],
        'metadata_arg_truthy_for_json_load' => $argumentPlan['semantic_boundaries']['metadata_file_truthy_for_json_load'],
        'metadata_file_input' => $runtimePlan['metadata']['metadata_file_input'],
        'metadata_file' => $runtimePlan['metadata']['metadata_file'],
        'metadata_file_is_dash_literal' => $runtimePlan['metadata']['metadata_file_is_dash_literal'],
        'metadata_file_dash_treated_as_stdin' => $runtimePlan['metadata']['metadata_file_dash_treated_as_stdin'],
        'metadata_file_stdin_read' => $runtimePlan['metadata']['metadata_file_stdin_read'],
        'metadata_file_open_uses_filesystem_path' => $runtimePlan['metadata']['metadata_file_open_uses_filesystem_path'],
        'metadata_file_input_folder_candidate_exists' => $runtimePlan['metadata']['metadata_file_input_folder_candidate_exists'],
        'metadata_file_output_folder_candidate_exists' => $runtimePlan['metadata']['metadata_file_output_folder_candidate_exists'],
        'metadata_loaded_filenames' => $runtimePlan['metadata']['metadata_filenames'],
        'selected_metadata_filenames' => $runtimePlan['metadata']['selected_metadata_filenames'],
        'missing_metadata_filenames' => $runtimePlan['metadata']['missing_metadata_filenames'],
        'task_metadata_title' => $taskArgsByName['dash.pdf']['metadata']['title'],
        'task_args_count' => $runtimePlan['worker_pool']['task_args_count'],
        'pool_launchable' => $runtimePlan['worker_pool']['pool_launchable'],
        'executes_python_or_models' => false,
        'executes_multiprocessing' => false,
        'executes_external_pdf_tools' => false,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($root);
}
