<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-runtime-input-broken-link-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerPDF broken-input-symlink boundary folder.');
    }

    return $path;
};

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

return [
    'records broken input-folder symlink listdir failure before output metadata and model handoff' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $brokenInput = $root . DIRECTORY_SEPARATOR . 'uploads-link';
            if (!@symlink($root . DIRECTORY_SEPARATOR . 'missing-uploads-target', $brokenInput)) {
                throw new RuntimeException('Unable to create broken input symlink fixture.');
            }
            $output = $root . DIRECTORY_SEPARATOR . 'marker-output';
            mkdir($output);
            $metadataFile = $root . DIRECTORY_SEPARATOR . 'metadata.json';
            file_put_contents($metadataFile, json_encode([
                'queued.pdf' => ['title' => 'Should Not Load'],
            ], JSON_THROW_ON_ERROR));

            $plan = (new BatchConverter())->runtimeMainPreflightErrorBoundary(
                $brokenInput,
                $output,
                metadataFile: $metadataFile,
                workers: 3,
                torchDevice: 'cuda',
                torchDeviceModel: 'cpu'
            );

            $t->same(false, $plan['success']);
            $t->same('input-folder-list-failed', $plan['error_boundary']);
            $t->same('FileNotFoundError', $plan['error_class']);
            $t->contains('No such file or directory', (string) $plan['upstream_error_message']);
            $t->same(false, $plan['paths']['input_path_exists']);
            $t->same('broken-symlink', $plan['paths']['input_path_type']);

            $listdir = $plan['paths']['path_resolution']['input_folder_listdir_boundary_review'];
            $t->same('convert.py os.listdir input-folder boundary', $listdir['source']);
            $t->same('after_abspath_before_output_makedirs', $listdir['order']);
            $t->same($brokenInput, $listdir['input_folder']);
            $t->same(false, $listdir['input_path_exists']);
            $t->same('broken-symlink', $listdir['input_path_type']);
            $t->same(true, $listdir['input_folder_is_symlink']);
            $t->same(false, $listdir['input_folder_symlink_target_exists']);
            $t->same(true, $listdir['input_folder_broken_symlink']);
            $t->same(true, $listdir['listdir_reached']);
            $t->same(false, $listdir['listdir_success']);
            $t->same('input-folder-list-failed', $listdir['error_boundary']);
            $t->same('broken-symlink', $listdir['error_reason']);
            $t->same('FileNotFoundError', $listdir['error_class']);
            $t->contains('No such file or directory', (string) $listdir['upstream_error_message_preview']);
            $t->same(true, $listdir['output_creation_blocked_by_listdir_failure']);
            $t->same(true, $listdir['metadata_load_blocked_by_listdir_failure']);
            $t->same(true, $listdir['model_handoff_blocked_by_listdir_failure']);
            $t->same(true, $listdir['task_args_blocked_by_listdir_failure']);

            $t->same(false, $plan['paths']['output_folder_creation_reached']);
            $t->same(false, $plan['metadata']['metadata_load_reached']);
            $t->same(false, $plan['spawn_start_method']['start_method_reached']);
            $t->same(false, $plan['model_handoff']['model_handoff_reached']);
            $t->same(false, $plan['console_summary']['summary_reached']);
            $t->same(0, $plan['worker_pool']['task_args_count']);
            $t->same(false, $plan['worker_pool']['pool_launchable']);
            $t->same('input-folder-list-failed', $plan['worker_pool']['pool_error_boundary']);
            $t->same([
                'makedirs_output_exist_ok',
                'chunk_files',
                'load_metadata_file',
                'set_spawn_start_method',
                'prepare_model_handoff',
                'print_conversion_summary',
                'build_task_args',
                'pool_imap_process_single_pdf',
            ], $plan['blocked_stages']);
            $t->same(false, $plan['executes_python_or_models']);
            $t->same(false, $plan['executes_multiprocessing']);
            $t->same(false, $plan['executes_external_pdf_tools']);
        } finally {
            $removeTree($root);
        }
    },
];
