<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-runtime-early-error-order-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerPDF runtime early-error order folder.');
    }

    return $path;
};

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

$runtimeDirectoryOrder = static function (string $path, bool $filesOnly = false): array {
    $handle = opendir($path);
    if ($handle === false) {
        throw new RuntimeException('Unable to inspect runtime early-error order directory.');
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

return [
    'preserves os.listdir order metadata across convert.py early runtime error branches' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree, $runtimeDirectoryOrder): void {
        $root = $makeTempDir();
        try {
            $input = $root . DIRECTORY_SEPARATOR . 'uploads';
            $output = $root . DIRECTORY_SEPARATOR . 'marker-output';
            mkdir($input);
            mkdir($output);

            foreach (['03-tail.pdf', '01-head.pdf', 'wp-notes.txt', '02-middle.pdf'] as $filename) {
                file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% {$filename}\n%%EOF");
            }
            mkdir($input . DIRECTORY_SEPARATOR . 'nested.pdf');

            $entryOrder = $runtimeDirectoryOrder($input);
            $fileOrder = $runtimeDirectoryOrder($input, filesOnly: true);
            $nonPdfFiles = array_values(array_filter(
                $fileOrder,
                static fn (string $filename): bool => !str_ends_with(strtolower($filename), '.pdf')
            ));

            $blockedOutput = $root . DIRECTORY_SEPARATOR . 'blocked-output';
            file_put_contents($blockedOutput, 'not a directory');
            $shapeMetadata = $root . DIRECTORY_SEPARATOR . 'shape-metadata.json';
            file_put_contents($shapeMetadata, '["not keyed by basename"]');

            $batch = new BatchConverter();
            $plans = [
                'output-folder-create-failed' => $batch->runtimeMainPreflightPlan(
                    $input,
                    $blockedOutput,
                    workers: 4
                ),
                'metadata-file-load-failed' => $batch->runtimeMainPreflightPlan(
                    $input,
                    $output,
                    workers: 4,
                    metadataFile: $root . DIRECTORY_SEPARATOR . 'missing-metadata.json'
                ),
                'metadata-get-failed' => $batch->runtimeMainPreflightPlan(
                    $input,
                    $output,
                    workers: 4,
                    metadataFile: $shapeMetadata
                ),
                'spawn-start-method-failed' => $batch->runtimeMainPreflightPlan(
                    $input,
                    $output,
                    workers: 4,
                    spawnStartMethodAlreadySet: true
                ),
            ];

            foreach ($plans as $expectedBoundary => $plan) {
                $listing = $plan['input_listing'];
                $t->same('os.listdir + os.path.isfile', $listing['source']);
                $t->same('os.listdir filesystem order', $listing['entry_order_source']);
                $t->same(false, $listing['sort_applied_before_chunking']);
                $t->same(true, $listing['preserves_os_listdir_order']);
                $t->same($entryOrder, $listing['entry_basenames']);
                $t->same($fileOrder, $listing['file_basenames']);
                $t->same($nonPdfFiles, $listing['non_pdf_file_basenames']);
                $t->same(false, $listing['extension_filter_active']);
                $t->same(false, $plan['executes_python_or_models']);
                $t->same(false, $plan['executes_multiprocessing']);
                $t->same(false, $plan['executes_external_pdf_tools']);

                if ($expectedBoundary === 'output-folder-create-failed') {
                    $t->same($expectedBoundary, $plan['worker_pool']['pool_error_boundary']);
                    $t->same(false, $plan['chunking']['chunking_reached']);
                    continue;
                }

                if ($expectedBoundary === 'metadata-file-load-failed') {
                    $t->same($expectedBoundary, $plan['metadata']['metadata_error_boundary']);
                    $t->same($expectedBoundary, $plan['worker_pool']['pool_error_boundary']);
                    continue;
                }

                if ($expectedBoundary === 'metadata-get-failed') {
                    $t->same($expectedBoundary, $plan['worker_pool']['pool_error_boundary']);
                    $t->same($fileOrder, $plan['chunking']['selected_filenames']);
                    continue;
                }

                $t->same($expectedBoundary, $plan['worker_pool']['pool_error_boundary']);
                $t->same(false, $plan['spawn_start_method']['start_method_success']);
            }
        } finally {
            $removeTree($root);
        }
    },
];
