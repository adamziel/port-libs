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

$root = sys_get_temp_dir() . '/markerpdf-runtime-metadata-basename-' . bin2hex(random_bytes(4));
$input = $root . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'uploads';
$output = $root . DIRECTORY_SEPARATOR . 'marker-output';

try {
    mkdir($input, 0777, true);
    mkdir($output, 0777, true);

    foreach (['absolute-only.pdf', 'editorial.pdf', 'windows-only.pdf'] as $filename) {
        file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% WordPress metadata basename {$filename}\n%%EOF");
    }

    $absoluteEditorialKey = $input . DIRECTORY_SEPARATOR . 'editorial.pdf';
    $relativeAbsoluteOnlyKey = 'wp-content/uploads/absolute-only.pdf';
    $windowsOnlyKey = 'C:\\wp\\uploads\\windows-only.pdf';
    $metadataFile = $output . DIRECTORY_SEPARATOR . 'metadata.json';
    file_put_contents($metadataFile, json_encode([
        $absoluteEditorialKey => ['title' => 'Absolute Path Decoy'],
        'editorial.pdf' => ['title' => 'Basename Editorial Import', 'languages' => ['English']],
        $relativeAbsoluteOnlyKey => ['title' => 'Relative Path Decoy'],
        $windowsOnlyKey => ['title' => 'Windows Path Decoy'],
    ], JSON_THROW_ON_ERROR));

    $plan = (new BatchConverter())->runtimeMainPreflightPlan(
        $input,
        $output,
        workers: 4,
        metadataFile: $metadataFile,
        torchDevice: 'cuda',
        torchDeviceModel: 'cpu'
    );

    $taskMetadataByFilename = [];
    foreach ($plan['worker_pool']['task_args'] as $taskArg) {
        $taskMetadataByFilename[basename((string) $taskArg['filepath'])] = $taskArg['metadata'];
    }

    $review = $plan['metadata']['metadata_basename_lookup_review'];
    if (($taskMetadataByFilename['editorial.pdf']['title'] ?? null) !== 'Basename Editorial Import') {
        throw new RuntimeException('Expected exact basename metadata to beat an absolute-path decoy key.');
    }
    if (!array_key_exists('absolute-only.pdf', $taskMetadataByFilename) || $taskMetadataByFilename['absolute-only.pdf'] !== null) {
        throw new RuntimeException('Expected relative path metadata key to be excluded from basename lookup.');
    }
    if (!array_key_exists('windows-only.pdf', $taskMetadataByFilename) || $taskMetadataByFilename['windows-only.pdf'] !== null) {
        throw new RuntimeException('Expected Windows path metadata key to be excluded from basename lookup.');
    }
    if (!$review['path_like_metadata_values_excluded_from_task_args']) {
        throw new RuntimeException('Expected path-like metadata exclusion review to be active.');
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-metadata-basename-lookup-currentbase',
        'purpose' => 'Review convert.py metadata_file JSON basename lookup for WordPress upload paths before Marker model workers launch.',
        'source' => $plan['source'],
        'upstream_boundary' => 'convert.py task_args metadata.get(os.path.basename(f))',
        'metadata_json_type' => $plan['metadata']['metadata_json_type'],
        'metadata_get_available' => $plan['metadata']['metadata_get_available'],
        'basename_only_lookup_preserved' => $review['basename_only_lookup_preserved'],
        'path_like_metadata_keys_found' => $review['path_like_metadata_keys_found'],
        'path_like_metadata_key_count' => $review['path_like_metadata_key_count'],
        'path_like_metadata_keys_with_selected_basenames' => $review['path_like_metadata_keys_with_selected_basenames'],
        'path_like_metadata_values_excluded_from_task_args' => $review['path_like_metadata_values_excluded_from_task_args'],
        'exact_basename_keys_with_path_like_decoys' => $review['exact_basename_keys_with_path_like_decoys'],
        'missing_metadata_filenames_due_to_path_like_keys' => $review['missing_metadata_filenames_due_to_path_like_keys'],
        'editorial_metadata_title' => $taskMetadataByFilename['editorial.pdf']['title'] ?? null,
        'absolute_path_metadata_excluded' => $taskMetadataByFilename['absolute-only.pdf'] === null,
        'windows_path_metadata_excluded' => $taskMetadataByFilename['windows-only.pdf'] === null,
        'pool_launchable' => $plan['worker_pool']['pool_launchable'],
        'task_args_count' => $plan['worker_pool']['task_args_count'],
        'executes_python_or_models' => $plan['executes_python_or_models'],
        'executes_multiprocessing' => $plan['executes_multiprocessing'],
        'executes_external_pdf_tools' => $plan['executes_external_pdf_tools'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($root);
}
