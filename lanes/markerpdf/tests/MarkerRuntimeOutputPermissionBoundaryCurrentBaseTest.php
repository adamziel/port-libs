<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-runtime-output-permission-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerPDF output-permission boundary folder.');
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
        throw new RuntimeException('Unable to inspect temporary output-permission directory order.');
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
    'records unwritable output parent permission errors before chunk metadata and model handoff' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree, $runtimeDirectoryOrder): void {
        $root = $makeTempDir();
        $lockedParent = $root . DIRECTORY_SEPARATOR . 'locked-output-parent';
        try {
            $input = $root . DIRECTORY_SEPARATOR . 'uploads';
            mkdir($input);
            foreach (['ready.pdf', 'sidecar.txt'] as $filename) {
                file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% {$filename}\n%%EOF");
            }
            $entryOrder = $runtimeDirectoryOrder($input);
            $fileOrder = $runtimeDirectoryOrder($input, filesOnly: true);

            mkdir($lockedParent);
            chmod($lockedParent, 0500);
            $output = $lockedParent . DIRECTORY_SEPARATOR . 'marker-output';
            $metadataFile = $root . DIRECTORY_SEPARATOR . 'metadata.json';
            file_put_contents($metadataFile, json_encode([
                'ready.pdf' => ['title' => 'Ready WordPress Import'],
            ], JSON_THROW_ON_ERROR));

            $plan = (new BatchConverter())->runtimeMainPreflightPlan(
                $input,
                $output,
                metadataFile: $metadataFile,
                workers: 4,
                torchDevice: 'cuda',
                torchDeviceModel: 'cpu'
            );

            $paths = $plan['paths'];
            $t->same('markerpdf.convert_main_runtime_preflight.v1', $plan['schema']);
            $t->same(true, $paths['output_folder_creation_required']);
            $t->same(true, $paths['output_folder_creation_blocked']);
            $t->same('output-folder-parent-permission-denied', $paths['output_folder_creation_error_boundary']);
            $t->same('PermissionError', $paths['output_folder_creation_error_class']);
            $t->contains('Permission denied', (string) $paths['output_folder_creation_error_message']);
            $t->same($lockedParent, $paths['output_folder_creation_permission_path']);
            $t->same('directory', $paths['output_folder_creation_permission_path_type']);
            $t->same(false, $paths['output_folder_creation_parent_writable']);
            $t->same(true, $paths['output_folder_creation_parent_searchable']);
            $t->same(true, $paths['output_folder_parent_permission_blocked']);
            $t->same(false, is_dir($output));

            $t->same($entryOrder, $plan['input_listing']['entry_basenames']);
            $t->same($fileOrder, $plan['input_listing']['file_basenames']);
            $t->same('output-folder-create-failed', $plan['chunking']['chunk_error_boundary']);
            $t->same(false, $plan['chunking']['chunking_reached']);
            $t->same(0, $plan['chunking']['selected_count']);
            $t->same([], $plan['chunking']['selected_filenames']);
            $t->same($metadataFile, $plan['metadata']['metadata_file']);
            $t->same(false, $plan['metadata']['metadata_load_reached']);
            $t->same(false, $plan['spawn_start_method']['start_method_reached']);
            $t->same('output-folder-create-failed', $plan['model_handoff']['blocked_by']);
            $t->same(false, $plan['model_handoff']['model_handoff_reached']);
            $t->same(false, $plan['model_handoff']['upstream_model_execution_required']);
            $t->same(0, $plan['worker_pool']['task_args_count']);
            $t->same(false, $plan['worker_pool']['pool_launchable']);
            $t->same('output-folder-create-failed', $plan['worker_pool']['pool_error_boundary']);
            $t->same(false, $plan['console_summary']['summary_reached']);
            $t->same(false, $plan['executes_python_or_models']);
            $t->same(false, $plan['executes_multiprocessing']);
            $t->same(false, $plan['executes_external_pdf_tools']);
        } finally {
            if (is_dir($lockedParent)) {
                chmod($lockedParent, 0700);
            }
            $removeTree($root);
        }
    },
];
