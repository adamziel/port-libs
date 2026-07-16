<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runId = bin2hex(random_bytes(4));
$root = sys_get_temp_dir() . '/markerpdf-input-broken-link-smoke-' . $runId;

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!file_exists($path) && !is_link($path)) {
        return;
    }
    if (is_link($path) || !is_dir($path)) {
        unlink($path);
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $removeTree($path . DIRECTORY_SEPARATOR . $entry);
    }

    rmdir($path);
};

if (!mkdir($root, 0777, true) && !is_dir($root)) {
    throw new RuntimeException('Unable to create markerPDF broken input symlink smoke root.');
}

try {
    $brokenInput = $root . DIRECTORY_SEPARATOR . 'uploads-link';
    if (!@symlink($root . DIRECTORY_SEPARATOR . 'missing-uploads-target', $brokenInput)) {
        throw new RuntimeException('Unable to create broken WordPress uploads symlink fixture.');
    }
    $output = $root . DIRECTORY_SEPARATOR . 'marker-output';
    mkdir($output);
    $metadataFile = $root . DIRECTORY_SEPARATOR . 'metadata.json';
    file_put_contents($metadataFile, json_encode([
        'queued.pdf' => ['title' => 'Broken Link Decoy'],
    ], JSON_THROW_ON_ERROR));

    $plan = (new BatchConverter())->runtimeMainPreflightErrorBoundary(
        $brokenInput,
        $output,
        metadataFile: $metadataFile,
        workers: 3,
        torchDevice: 'cuda',
        torchDeviceModel: 'cpu'
    );
    $listdir = $plan['paths']['path_resolution']['input_folder_listdir_boundary_review'] ?? [];

    if (
        $plan['success'] !== false
        || $plan['error_boundary'] !== 'input-folder-list-failed'
        || $plan['error_class'] !== 'FileNotFoundError'
        || ($plan['paths']['input_path_type'] ?? null) !== 'broken-symlink'
        || ($listdir['error_reason'] ?? null) !== 'broken-symlink'
        || ($listdir['output_creation_blocked_by_listdir_failure'] ?? null) !== true
        || ($plan['metadata']['metadata_load_reached'] ?? true) !== false
        || ($plan['model_handoff']['model_handoff_reached'] ?? true) !== false
        || ($plan['worker_pool']['task_args_count'] ?? null) !== 0
    ) {
        throw new RuntimeException('Expected broken input-folder symlink to fail at os.listdir before output, metadata, model, and pool stages.');
    }

    echo '<!-- markerpdf-runtime-input-broken-symlink-currentbase ' . htmlspecialchars(json_encode([
        'scenario' => 'wordpress-marker-runtime-input-broken-symlink-currentbase',
        'purpose' => 'Review a WordPress uploads directory symlink that breaks before markerPDF convert.py reaches output creation, metadata loading, model handoff, task args, or multiprocessing.',
        'source_truth' => 'sddai/markerPDF convert.py calls os.listdir(in_folder) before os.makedirs(out_folder, exist_ok=True), metadata_file json.load, model handoff, task_args, or mp.Pool.',
        'input_path_type' => $plan['paths']['input_path_type'],
        'listdir_error_reason' => $listdir['error_reason'],
        'listdir_error_class' => $listdir['error_class'],
        'blocked_stages' => $plan['blocked_stages'],
        'metadata_load_reached' => $plan['metadata']['metadata_load_reached'],
        'model_handoff_reached' => $plan['model_handoff']['model_handoff_reached'],
        'task_args_count' => $plan['worker_pool']['task_args_count'],
        'executes_python_or_models' => $plan['executes_python_or_models'],
        'executes_multiprocessing' => $plan['executes_multiprocessing'],
        'executes_external_pdf_tools' => $plan['executes_external_pdf_tools'],
    ], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
} finally {
    $removeTree($root);
}
