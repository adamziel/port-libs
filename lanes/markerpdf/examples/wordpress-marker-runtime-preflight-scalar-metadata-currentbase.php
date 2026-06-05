<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runId = bin2hex(random_bytes(4));
$input = sys_get_temp_dir() . '/markerpdf-runtime-scalar-metadata-input-' . $runId;
$output = sys_get_temp_dir() . '/markerpdf-runtime-scalar-metadata-output-' . $runId;
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
    foreach (['editorial.pdf', 'translation.pdf'] as $filename) {
        file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% {$filename}\n%%EOF");
    }

    $scalarMetadataFile = $output . DIRECTORY_SEPARATOR . 'metadata-scalar.json';
    file_put_contents($scalarMetadataFile, '1.5');
    $objectMetadataFile = $output . DIRECTORY_SEPARATOR . 'metadata-object.json';
    file_put_contents($objectMetadataFile, json_encode([
        'editorial.pdf' => ['title' => 'Editorial Import', 'priority' => 1.5],
        'translation.pdf' => ['title' => 'Translation Import', 'languages' => ['English']],
    ], JSON_THROW_ON_ERROR));

    $batch = new BatchConverter();
    $scalarPlan = $batch->runtimeMainPreflightPlan(
        $input,
        $output,
        workers: 5,
        metadataFile: $scalarMetadataFile,
        torchDevice: 'cuda',
        torchDeviceModel: 'cpu'
    );
    $objectPlan = $batch->runtimeMainPreflightPlan(
        $input,
        $output,
        workers: 5,
        metadataFile: $objectMetadataFile,
        torchDevice: 'cuda',
        torchDeviceModel: 'cpu'
    );

    $taskArgsByName = [];
    foreach ($objectPlan['worker_pool']['task_args'] as $taskArg) {
        $taskArgsByName[basename($taskArg['filepath'])] = $taskArg;
    }

    if ($scalarPlan['metadata']['metadata_json_type'] !== 'float') {
        throw new RuntimeException('Expected scalar metadata file JSON number with decimal to be reviewed as Python float.');
    }
    if ($scalarPlan['worker_pool']['task_args_count'] !== 0 || $scalarPlan['worker_pool']['pool_error_boundary'] !== 'metadata-get-failed') {
        throw new RuntimeException('Expected scalar metadata file to fail at metadata.get before task args.');
    }
    if (($objectPlan['metadata']['metadata_value_types']['editorial.pdf'] ?? null) !== 'dict') {
        throw new RuntimeException('Expected object metadata file to preserve per-file dict metadata.');
    }
    if (($taskArgsByName['editorial.pdf']['metadata']['priority'] ?? null) !== 1.5) {
        throw new RuntimeException('Expected per-file float metadata value to remain available to task args.');
    }
    if ($scalarPlan['executes_python_or_models'] !== false || $objectPlan['executes_python_or_models'] !== false) {
        throw new RuntimeException('Runtime scalar metadata smoke must not execute Python or models.');
    }
    if ($scalarPlan['executes_external_pdf_tools'] !== false || $objectPlan['executes_external_pdf_tools'] !== false) {
        throw new RuntimeException('Runtime scalar metadata smoke must not execute external PDF tools.');
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-preflight-scalar-metadata-currentbase',
        'purpose' => 'Record convert.py metadata_file scalar JSON boundaries for WordPress batch imports before task args, multiprocessing, pdftext, pypdfium, model loading, or external PDF tools.',
        'scalar_metadata_json_type' => $scalarPlan['metadata']['metadata_json_type'],
        'scalar_metadata_error_boundary' => $scalarPlan['metadata']['metadata_shape_error_boundary'],
        'scalar_metadata_error_message' => $scalarPlan['metadata']['metadata_shape_error_message'],
        'scalar_task_args_count' => $scalarPlan['worker_pool']['task_args_count'],
        'scalar_pool_error_boundary' => $scalarPlan['worker_pool']['pool_error_boundary'],
        'object_metadata_value_types' => $objectPlan['metadata']['metadata_value_types'],
        'editorial_priority_metadata' => $taskArgsByName['editorial.pdf']['metadata']['priority'] ?? null,
        'object_task_args_count' => $objectPlan['worker_pool']['task_args_count'],
        'object_pool_launchable' => $objectPlan['worker_pool']['pool_launchable'],
        'executes_python_or_models' => $scalarPlan['executes_python_or_models'] || $objectPlan['executes_python_or_models'],
        'executes_external_pdf_tools' => $scalarPlan['executes_external_pdf_tools'] || $objectPlan['executes_external_pdf_tools'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($input);
    $removeTree($output);
}
