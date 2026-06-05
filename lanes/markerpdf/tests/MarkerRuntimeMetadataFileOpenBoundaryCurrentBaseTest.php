<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-runtime-metadata-open-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf metadata-file boundary folder.');
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
        throw new RuntimeException('Unable to inspect runtime metadata-file boundary directory order.');
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
    'records metadata_file open path type before json load and model handoff' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree, $runtimeDirectoryOrder): void {
        $root = $makeTempDir();
        try {
            $input = $root . DIRECTORY_SEPARATOR . 'uploads';
            $output = $root . DIRECTORY_SEPARATOR . 'marker-output';
            mkdir($input);
            mkdir($output);

            foreach (['queued.pdf', 'missing-meta.pdf'] as $filename) {
                file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% {$filename}\n%%EOF");
            }
            $fileOrder = $runtimeDirectoryOrder($input, filesOnly: true);

            $metadataJson = $root . DIRECTORY_SEPARATOR . 'metadata-source.json';
            file_put_contents($metadataJson, json_encode([
                'queued.pdf' => ['title' => 'Queued Metadata'],
            ], JSON_THROW_ON_ERROR));
            $metadataLink = $root . DIRECTORY_SEPARATOR . 'metadata-link.json';
            if (!@symlink($metadataJson, $metadataLink)) {
                throw new RuntimeException('Unable to create metadata-file symlink fixture.');
            }
            $metadataDirectory = $root . DIRECTORY_SEPARATOR . 'metadata-directory.json';
            mkdir($metadataDirectory);

            $batch = new BatchConverter();
            $symlinkPlan = $batch->runtimeMainPreflightPlan(
                $input,
                $output,
                workers: 4,
                metadataFile: $metadataLink,
                torchDevice: 'cuda',
                torchDeviceModel: 'cpu'
            );
            $directoryPlan = $batch->runtimeMainPreflightPlan(
                $input,
                $output,
                workers: 4,
                metadataFile: $metadataDirectory,
                torchDevice: 'cuda',
                torchDeviceModel: 'cpu'
            );

            $symlinkMetadata = $symlinkPlan['metadata'];
            $t->same($metadataLink, $symlinkMetadata['metadata_file']);
            $t->same(true, $symlinkMetadata['metadata_file_path_exists']);
            $t->same('file', $symlinkMetadata['metadata_file_path_type']);
            $t->same(true, $symlinkMetadata['metadata_file_is_symlink']);
            $t->same(true, $symlinkMetadata['metadata_file_open_follows_symlink']);
            $t->same(true, $symlinkMetadata['metadata_file_symlink_target_exists']);
            $t->same('file', $symlinkMetadata['metadata_file_symlink_target_type']);
            $t->same('open(metadata_file, "r")', $symlinkMetadata['metadata_file_open_call']);
            $t->same('after_abspath_before_json_load', $symlinkMetadata['metadata_file_open_order']);
            $t->same(true, $symlinkMetadata['metadata_load_success']);
            $t->same(['queued.pdf'], $symlinkMetadata['metadata_filenames']);
            $t->same(['queued.pdf'], $symlinkMetadata['selected_metadata_filenames']);
            $t->same(['missing-meta.pdf'], $symlinkMetadata['missing_metadata_filenames']);
            $t->same($fileOrder, $symlinkPlan['chunking']['selected_filenames']);
            $t->same(2, $symlinkPlan['worker_pool']['task_args_count']);
            $t->same(true, $symlinkPlan['worker_pool']['pool_launchable']);

            $taskArgsByName = [];
            foreach ($symlinkPlan['worker_pool']['task_args'] as $taskArg) {
                $taskArgsByName[basename($taskArg['filepath'])] = $taskArg;
            }
            $t->same(['title' => 'Queued Metadata'], $taskArgsByName['queued.pdf']['metadata']);
            $t->same(null, $taskArgsByName['missing-meta.pdf']['metadata']);

            $directoryMetadata = $directoryPlan['metadata'];
            $t->same($metadataDirectory, $directoryMetadata['metadata_file']);
            $t->same(true, $directoryMetadata['metadata_file_path_exists']);
            $t->same('directory', $directoryMetadata['metadata_file_path_type']);
            $t->same(false, $directoryMetadata['metadata_file_is_symlink']);
            $t->same(false, $directoryMetadata['metadata_file_open_follows_symlink']);
            $t->same(false, $directoryMetadata['metadata_file_symlink_target_exists']);
            $t->same(null, $directoryMetadata['metadata_file_symlink_target_type']);
            $t->same('open(metadata_file, "r")', $directoryMetadata['metadata_file_open_call']);
            $t->same('after_abspath_before_json_load', $directoryMetadata['metadata_file_open_order']);
            $t->same(true, $directoryMetadata['metadata_load_reached']);
            $t->same(false, $directoryMetadata['metadata_load_success']);
            $t->same('metadata-file-load-failed', $directoryMetadata['metadata_error_boundary']);
            $t->same('IsADirectoryError', $directoryMetadata['metadata_error_class']);
            $t->contains('Is a directory', $directoryMetadata['metadata_error_message']);
            $t->same([], $directoryMetadata['metadata_filenames']);
            $t->same([], $directoryMetadata['selected_metadata_filenames']);
            $t->same([], $directoryMetadata['missing_metadata_filenames']);
            $t->same(false, $directoryPlan['spawn_start_method']['start_method_reached']);
            $t->same('metadata-file-load-failed', $directoryPlan['model_handoff']['blocked_by']);
            $t->same(0, $directoryPlan['worker_pool']['task_args_count']);
            $t->same('metadata-file-load-failed', $directoryPlan['worker_pool']['pool_error_boundary']);
            $t->same(false, $directoryPlan['console_summary']['summary_reached']);
            $t->same(false, $directoryPlan['executes_python_or_models']);
            $t->same(false, $directoryPlan['executes_multiprocessing']);
            $t->same(false, $directoryPlan['executes_external_pdf_tools']);
        } finally {
            $removeTree($root);
        }
    },
];
