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
        if (is_dir($child)) {
            $removeTree($child);
        } else {
            unlink($child);
        }
    }

    rmdir($path);
};

$root = sys_get_temp_dir() . '/markerpdf-runtime-numeric-metadata-' . bin2hex(random_bytes(4));
$input = $root . DIRECTORY_SEPARATOR . 'uploads';
$output = $root . DIRECTORY_SEPARATOR . 'output';

try {
    mkdir($input, 0777, true);
    mkdir($output, 0777, true);

    foreach (['0', '01', '2.pdf', 'missing'] as $filename) {
        file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% WordPress runtime numeric metadata {$filename}\n%%EOF");
    }

    $metadataFile = $output . DIRECTORY_SEPARATOR . 'metadata.json';
    file_put_contents($metadataFile, json_encode([
        '0' => ['title' => 'Zero Basename Import', 'languages' => ['English']],
        '01' => ['title' => 'Leading Zero Basename Import'],
        '2.pdf' => ['title' => 'Numeric Prefix PDF Import'],
    ], JSON_THROW_ON_ERROR));

    $plan = (new BatchConverter())->runtimeMainPreflightPlan(
        $input,
        $output,
        workers: 6,
        metadataFile: $metadataFile,
        torchDevice: 'cuda',
        torchDeviceModel: 'cpu'
    );

    $taskMetadataByName = [];
    foreach ($plan['worker_pool']['task_args'] as $taskArg) {
        $taskMetadataByName[basename((string) $taskArg['filepath'])] = $taskArg['metadata'];
    }

    if (($taskMetadataByName['0']['title'] ?? null) !== 'Zero Basename Import') {
        throw new RuntimeException('Expected numeric basename metadata key "0" to remain addressable.');
    }
    if (($taskMetadataByName['01']['title'] ?? null) !== 'Leading Zero Basename Import') {
        throw new RuntimeException('Expected leading-zero metadata key "01" to remain addressable.');
    }
    if (!array_key_exists('missing', $taskMetadataByName) || $taskMetadataByName['missing'] !== null) {
        throw new RuntimeException('Expected missing numeric metadata basename to preserve Python None/null lookup.');
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-metadata-numeric-key-currentbase',
        'purpose' => 'Review convert.py metadata_file JSON basename lookup for numeric-looking WordPress upload filenames before Marker model workers launch.',
        'source' => $plan['source'],
        'upstream_boundary' => 'convert.py json.load(metadata_file) + metadata.get(os.path.basename(f))',
        'metadata_json_type' => $plan['metadata']['metadata_json_type'],
        'metadata_get_available' => $plan['metadata']['metadata_get_available'],
        'metadata_filenames' => $plan['metadata']['metadata_filenames'],
        'selected_metadata_filenames' => $plan['metadata']['selected_metadata_filenames'],
        'missing_metadata_filenames' => $plan['metadata']['missing_metadata_filenames'],
        'numeric_zero_metadata_title' => $taskMetadataByName['0']['title'] ?? null,
        'leading_zero_metadata_title' => $taskMetadataByName['01']['title'] ?? null,
        'numeric_pdf_metadata_title' => $taskMetadataByName['2.pdf']['title'] ?? null,
        'missing_metadata_is_null' => array_key_exists('missing', $taskMetadataByName) && $taskMetadataByName['missing'] === null,
        'pool_launchable' => $plan['worker_pool']['pool_launchable'],
        'task_args_count' => $plan['worker_pool']['task_args_count'],
        'executes_python_or_models' => $plan['executes_python_or_models'],
        'executes_multiprocessing' => $plan['executes_multiprocessing'],
        'executes_external_pdf_tools' => $plan['executes_external_pdf_tools'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($root);
}
