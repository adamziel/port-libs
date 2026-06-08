<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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

$root = sys_get_temp_dir() . '/markerpdf-runtime-tilde-path-smoke-' . bin2hex(random_bytes(4));
$previousCwd = getcwd();
if (!is_string($previousCwd)) {
    throw new RuntimeException('Unable to capture cwd for markerPDF runtime tilde path smoke.');
}

try {
    $tildeRoot = $root . DIRECTORY_SEPARATOR . '~';
    $input = $tildeRoot . DIRECTORY_SEPARATOR . 'wp-uploads';
    $output = $tildeRoot . DIRECTORY_SEPARATOR . 'marker-output';
    mkdir($input, 0777, true);
    mkdir($output, 0777, true);

    file_put_contents($input . DIRECTORY_SEPARATOR . 'wp-tilde-report.pdf', "%PDF-1.4\n% WordPress tilde report\n%%EOF");
    file_put_contents($input . DIRECTORY_SEPARATOR . 'wp-tilde-sidecar.txt', 'WordPress upload sidecar');
    $metadataPath = $tildeRoot . DIRECTORY_SEPARATOR . 'metadata.json';
    file_put_contents($metadataPath, json_encode([
        'wp-tilde-report.pdf' => [
            'title' => 'Literal Tilde WordPress Import',
            'languages' => ['English'],
        ],
    ], JSON_THROW_ON_ERROR));

    if (!chdir($root)) {
        throw new RuntimeException('Unable to enter markerPDF runtime tilde path smoke root.');
    }

    $plan = (new BatchConverter())->runtimeMainPreflightPlan(
        '~/wp-uploads',
        '~/marker-output',
        workers: 3,
        metadataFile: '~/metadata.json',
        torchDevice: 'cuda',
        torchDeviceModel: 'cpu'
    );

    $taskMetadataByFilename = [];
    foreach ($plan['worker_pool']['task_args'] as $taskArg) {
        $taskMetadataByFilename[basename((string) $taskArg['filepath'])] = $taskArg['metadata'];
    }

    $resolution = $plan['paths']['path_resolution'];
    $metadata = $plan['metadata'];
    $result = [
        'scenario' => 'wordpress-marker-runtime-tilde-path-boundary-currentbase',
        'source_truth' => 'sddai/markerPDF convert.py uses os.path.abspath for input, output, and metadata_file paths without expanduser before task args or model workers.',
        'input_tilde_literal' => $resolution['input_folder_has_leading_tilde'] === true
            && $resolution['input_folder_tilde_expanded_to_home'] === false
            && $resolution['absolute_input_folder'] === $input,
        'output_tilde_literal' => $resolution['output_folder_has_leading_tilde'] === true
            && $resolution['output_folder_tilde_expanded_to_home'] === false
            && $resolution['absolute_output_folder'] === $output,
        'metadata_tilde_literal' => $metadata['metadata_file_has_leading_tilde'] === true
            && $metadata['metadata_file_tilde_expanded_to_home'] === false
            && $metadata['metadata_file'] === $metadataPath,
        'literal_tilde_segment_preserved' => $resolution['literal_tilde_segment_preserved'],
        'metadata_loaded_from_process_cwd_tilde' => $metadata['metadata_load_success'] === true
            && ($taskMetadataByFilename['wp-tilde-report.pdf']['title'] ?? null) === 'Literal Tilde WordPress Import',
        'sidecar_remains_task_candidate_before_worker_preflight' => in_array(
            'wp-tilde-sidecar.txt',
            $plan['input_listing']['selected_non_pdf_filenames'],
            true
        ),
        'pool_launchable' => $plan['worker_pool']['pool_launchable'],
        'task_args_count' => $plan['worker_pool']['task_args_count'],
        'executes_python_or_models' => $plan['executes_python_or_models'],
        'executes_multiprocessing' => $plan['executes_multiprocessing'],
        'executes_external_pdf_tools' => $plan['executes_external_pdf_tools'],
    ];

    if (
        $result['input_tilde_literal'] !== true
        || $result['output_tilde_literal'] !== true
        || $result['metadata_tilde_literal'] !== true
        || $result['literal_tilde_segment_preserved'] !== true
        || $result['metadata_loaded_from_process_cwd_tilde'] !== true
        || $result['sidecar_remains_task_candidate_before_worker_preflight'] !== true
        || $result['pool_launchable'] !== true
        || $result['executes_python_or_models'] !== false
        || $result['executes_multiprocessing'] !== false
        || $result['executes_external_pdf_tools'] !== false
    ) {
        throw new RuntimeException('MarkerPDF runtime tilde path boundary smoke failed.');
    }

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    chdir($previousCwd);
    $removeTree($root);
}
