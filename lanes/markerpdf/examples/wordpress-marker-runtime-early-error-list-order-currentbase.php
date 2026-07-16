<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$root = sys_get_temp_dir() . '/markerpdf-runtime-early-error-order-smoke-' . bin2hex(random_bytes(4));
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

$directoryOrder = static function (string $path, bool $filesOnly = false): array {
    $handle = opendir($path);
    if ($handle === false) {
        throw new RuntimeException('Unable to inspect runtime early-error order smoke directory.');
    }

    $entries = [];
    try {
        while (($entry = readdir($handle)) !== false) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if ($filesOnly && !is_file($path . DIRECTORY_SEPARATOR . $entry)) {
                continue;
            }

            $entries[] = $entry;
        }
    } finally {
        closedir($handle);
    }

    return $entries;
};

try {
    mkdir($input, 0777, true);
    mkdir($output, 0777, true);

    foreach (['03-tail.pdf', '01-head.pdf', 'wp-notes.txt', '02-middle.pdf'] as $filename) {
        file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% {$filename}\n%%EOF");
    }
    mkdir($input . DIRECTORY_SEPARATOR . 'nested.pdf');

    $entryOrder = $directoryOrder($input);
    $fileOrder = $directoryOrder($input, filesOnly: true);
    $blockedOutput = $root . DIRECTORY_SEPARATOR . 'blocked-output';
    file_put_contents($blockedOutput, 'not a directory');
    $shapeMetadata = $root . DIRECTORY_SEPARATOR . 'shape-metadata.json';
    file_put_contents($shapeMetadata, '["not keyed by basename"]');

    $batch = new BatchConverter();
    $plans = [
        'output-folder-create-failed' => $batch->runtimeMainPreflightPlan($input, $blockedOutput, workers: 4),
        'metadata-get-failed' => $batch->runtimeMainPreflightPlan($input, $output, workers: 4, metadataFile: $shapeMetadata),
        'spawn-start-method-failed' => $batch->runtimeMainPreflightPlan($input, $output, workers: 4, spawnStartMethodAlreadySet: true),
    ];

    $branchesPreserveOrder = true;
    $branchesPreserveFileOrder = true;
    $branchBoundaries = [];
    foreach ($plans as $expectedBoundary => $plan) {
        $listing = $plan['input_listing'];
        $branchesPreserveOrder = $branchesPreserveOrder
            && ($listing['entry_order_source'] ?? null) === 'os.listdir filesystem order'
            && ($listing['sort_applied_before_chunking'] ?? null) === false
            && ($listing['preserves_os_listdir_order'] ?? null) === true
            && ($listing['entry_basenames'] ?? null) === $entryOrder;
        $branchesPreserveFileOrder = $branchesPreserveFileOrder
            && ($listing['file_basenames'] ?? null) === $fileOrder;
        $branchBoundaries[$expectedBoundary] = $plan['worker_pool']['pool_error_boundary'] ?? null;
    }

    if (!$branchesPreserveOrder || !$branchesPreserveFileOrder) {
        throw new RuntimeException('Runtime early-error order metadata was not preserved.');
    }

    echo '<!-- markerpdf-runtime-early-error-list-order-currentbase ' . htmlspecialchars(json_encode([
        'scenario' => 'wordpress-marker-runtime-early-error-list-order-currentbase',
        'purpose' => 'Review convert.py input listing order metadata when later runtime preflight stages fail before Marker model workers launch.',
        'source_truth' => 'sddai/markerPDF convert.py lists os.listdir(in_folder), filters os.path.isfile, and preserves that list through output-folder, metadata, spawn, and task-arg boundaries.',
        'entry_order_source' => 'os.listdir filesystem order',
        'entry_order_preserved_on_early_errors' => $branchesPreserveOrder,
        'file_order_preserved_on_early_errors' => $branchesPreserveFileOrder,
        'branch_boundaries' => $branchBoundaries,
        'executes_python_or_models' => false,
        'executes_multiprocessing' => false,
        'executes_external_pdf_tools' => false,
    ], JSON_THROW_ON_ERROR), ENT_NOQUOTES, 'UTF-8') . " -->\n";
} finally {
    $removeTree($root);
}
