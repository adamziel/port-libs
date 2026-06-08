<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-runtime-metadata-broken-symlink-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf broken metadata symlink folder.');
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
        throw new RuntimeException('Unable to inspect runtime metadata broken-symlink directory order.');
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
    'records broken metadata_file symlink open failure after chunking before spawn' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree, $runtimeDirectoryOrder): void {
        $root = $makeTempDir();
        try {
            $input = $root . DIRECTORY_SEPARATOR . 'uploads';
            $output = $root . DIRECTORY_SEPARATOR . 'marker-output';
            mkdir($input);
            mkdir($output);

            foreach (['queued.pdf', 'review-sidecar.txt'] as $filename) {
                file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% {$filename}\n%%EOF");
            }
            $fileOrder = $runtimeDirectoryOrder($input, filesOnly: true);
            $metadataLink = $root . DIRECTORY_SEPARATOR . 'metadata-broken-link.json';
            $missingTarget = $root . DIRECTORY_SEPARATOR . 'missing-wordpress-metadata.json';
            if (!@symlink($missingTarget, $metadataLink)) {
                throw new RuntimeException('Unable to create broken metadata-file symlink fixture.');
            }

            $plan = (new BatchConverter())->runtimeMainPreflightPlan(
                $input,
                $output,
                workers: 4,
                metadataFile: $metadataLink,
                torchDevice: 'cuda',
                torchDeviceModel: 'cpu'
            );

            $metadata = $plan['metadata'];
            $t->same($fileOrder, $plan['chunking']['selected_filenames']);
            $t->same(true, $plan['chunking']['chunking_reached']);
            $t->same(null, $plan['chunking']['chunk_error_boundary']);
            $t->same($metadataLink, $metadata['metadata_file']);
            $t->same($metadataLink, $metadata['metadata_file_input']);
            $t->same('open(metadata_file, "r")', $metadata['metadata_file_open_call']);
            $t->same('after_abspath_before_json_load', $metadata['metadata_file_open_order']);
            $t->same(true, $metadata['metadata_file_open_uses_filesystem_path']);
            $t->same(false, $metadata['metadata_file_path_exists']);
            $t->same('broken-symlink', $metadata['metadata_file_path_type']);
            $t->same(true, $metadata['metadata_file_is_symlink']);
            $t->same(true, $metadata['metadata_file_open_follows_symlink']);
            $t->same(false, $metadata['metadata_file_symlink_target_exists']);
            $t->same('missing', $metadata['metadata_file_symlink_target_type']);
            $t->same(true, $metadata['metadata_file_broken_symlink']);
            $t->same(true, $metadata['metadata_file_open_broken_symlink_fails']);
            $t->same(true, $metadata['metadata_load_reached']);
            $t->same(false, $metadata['metadata_load_success']);
            $t->same('metadata-file-load-failed', $metadata['metadata_error_boundary']);
            $t->same('FileNotFoundError', $metadata['metadata_error_class']);
            $t->contains('No such file or directory', $metadata['metadata_error_message']);
            $t->same([], $metadata['metadata_filenames']);
            $t->same([], $metadata['selected_metadata_filenames']);
            $t->same([], $metadata['missing_metadata_filenames']);

            $t->same(false, $plan['spawn_start_method']['start_method_reached']);
            $t->same('metadata-file-load-failed', $plan['spawn_start_method']['blocked_by']);
            $t->same(false, $plan['model_handoff']['model_handoff_reached']);
            $t->same('metadata-file-load-failed', $plan['model_handoff']['blocked_by']);
            $t->same(0, $plan['worker_pool']['task_args_count']);
            $t->same([], $plan['worker_pool']['task_args']);
            $t->same(false, $plan['worker_pool']['pool_launchable']);
            $t->same('metadata-file-load-failed', $plan['worker_pool']['pool_error_boundary']);
            $t->same(false, $plan['console_summary']['summary_reached']);
            $t->same('metadata-file-load-failed', $plan['console_summary']['blocked_by']);
            $t->same(false, $plan['executes_python_or_models']);
            $t->same(false, $plan['executes_multiprocessing']);
            $t->same(false, $plan['executes_external_pdf_tools']);
        } finally {
            $removeTree($root);
        }
    },
];
