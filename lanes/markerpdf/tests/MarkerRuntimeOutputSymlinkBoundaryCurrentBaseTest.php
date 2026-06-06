<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-runtime-output-symlink-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerPDF output-symlink boundary folder.');
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

return [
    'records output-folder symlink makedirs boundaries before chunk metadata and model handoff' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            $input = $root . DIRECTORY_SEPARATOR . 'uploads';
            mkdir($input);
            file_put_contents($input . DIRECTORY_SEPARATOR . 'queued.pdf', "%PDF-1.4\n% queued\n%%EOF");

            $realOutput = $root . DIRECTORY_SEPARATOR . 'real-output';
            mkdir($realOutput);
            $directorySymlink = $root . DIRECTORY_SEPARATOR . 'output-dir-link';
            if (!@symlink($realOutput, $directorySymlink)) {
                throw new RuntimeException('Unable to create directory symlink output fixture.');
            }

            $realOutputFile = $root . DIRECTORY_SEPARATOR . 'real-output-file';
            file_put_contents($realOutputFile, 'not a directory');
            $fileSymlink = $root . DIRECTORY_SEPARATOR . 'output-file-link';
            if (!@symlink($realOutputFile, $fileSymlink)) {
                throw new RuntimeException('Unable to create file symlink output fixture.');
            }

            $brokenSymlink = $root . DIRECTORY_SEPARATOR . 'output-broken-link';
            if (!@symlink($root . DIRECTORY_SEPARATOR . 'missing-output-target', $brokenSymlink)) {
                throw new RuntimeException('Unable to create broken symlink output fixture.');
            }

            $batch = new BatchConverter();
            $directoryPlan = $batch->runtimeMainPreflightPlan($input, $directorySymlink, workers: 2);
            $filePlan = $batch->runtimeMainPreflightPlan(
                $input,
                $fileSymlink,
                metadataFile: $root . DIRECTORY_SEPARATOR . 'missing-metadata.json',
                workers: 2,
                torchDevice: 'cuda',
                torchDeviceModel: 'cpu'
            );
            $brokenPlan = $batch->runtimeMainPreflightPlan(
                $input,
                $brokenSymlink,
                metadataFile: $root . DIRECTORY_SEPARATOR . 'missing-metadata.json',
                workers: 2,
                torchDevice: 'cuda',
                torchDeviceModel: 'cpu'
            );

            $directoryPaths = $directoryPlan['paths'];
            $t->same(true, $directoryPaths['output_path_exists']);
            $t->same('directory', $directoryPaths['output_path_type']);
            $t->same(true, $directoryPaths['output_folder_exists']);
            $t->same(true, $directoryPaths['output_folder_is_symlink']);
            $t->same(true, $directoryPaths['output_folder_makedirs_follows_symlink']);
            $t->same(true, $directoryPaths['output_folder_symlink_target_exists']);
            $t->same('directory', $directoryPaths['output_folder_symlink_target_type']);
            $t->same(false, $directoryPaths['output_folder_broken_symlink']);
            $t->same(false, $directoryPaths['output_folder_symlink_target_blocked']);
            $t->same(false, $directoryPaths['output_folder_creation_required']);
            $t->same(false, $directoryPaths['output_folder_creation_blocked']);
            $t->same(null, $directoryPaths['output_folder_creation_error_boundary']);
            $t->same(true, $directoryPlan['chunking']['chunking_reached']);
            $t->same(['queued.pdf'], $directoryPlan['chunking']['selected_filenames']);
            $t->same(1, $directoryPlan['worker_pool']['task_args_count']);
            $t->same($directorySymlink, $directoryPlan['worker_pool']['task_args'][0]['out_folder']);
            $t->same(true, $directoryPlan['worker_pool']['pool_launchable']);

            $filePaths = $filePlan['paths'];
            $t->same(true, $filePaths['output_path_exists']);
            $t->same('file', $filePaths['output_path_type']);
            $t->same(false, $filePaths['output_folder_exists']);
            $t->same(true, $filePaths['output_folder_is_symlink']);
            $t->same(false, $filePaths['output_folder_makedirs_follows_symlink']);
            $t->same(true, $filePaths['output_folder_symlink_target_exists']);
            $t->same('file', $filePaths['output_folder_symlink_target_type']);
            $t->same(false, $filePaths['output_folder_broken_symlink']);
            $t->same(true, $filePaths['output_folder_symlink_target_blocked']);
            $t->same(true, $filePaths['output_folder_creation_required']);
            $t->same(true, $filePaths['output_folder_creation_blocked']);
            $t->same('output-folder-target-exists-not-directory', $filePaths['output_folder_creation_error_boundary']);
            $t->same('FileExistsError', $filePaths['output_folder_creation_error_class']);
            $t->contains('File exists', (string) $filePaths['output_folder_creation_error_message']);
            $t->same(false, $filePlan['chunking']['chunking_reached']);
            $t->same(false, $filePlan['metadata']['metadata_load_reached']);
            $t->same(0, $filePlan['worker_pool']['task_args_count']);
            $t->same('output-folder-create-failed', $filePlan['worker_pool']['pool_error_boundary']);
            $t->same(false, $filePlan['model_handoff']['model_handoff_reached']);
            $t->same(false, $filePlan['executes_python_or_models']);

            $brokenPaths = $brokenPlan['paths'];
            $t->same(false, $brokenPaths['output_path_exists']);
            $t->same('broken-symlink', $brokenPaths['output_path_type']);
            $t->same(false, $brokenPaths['output_folder_exists']);
            $t->same(true, $brokenPaths['output_folder_is_symlink']);
            $t->same(false, $brokenPaths['output_folder_makedirs_follows_symlink']);
            $t->same(false, $brokenPaths['output_folder_symlink_target_exists']);
            $t->same('missing', $brokenPaths['output_folder_symlink_target_type']);
            $t->same(true, $brokenPaths['output_folder_broken_symlink']);
            $t->same(true, $brokenPaths['output_folder_symlink_target_blocked']);
            $t->same(true, $brokenPaths['output_folder_creation_required']);
            $t->same(true, $brokenPaths['output_folder_creation_blocked']);
            $t->same('output-folder-target-exists-not-directory', $brokenPaths['output_folder_creation_error_boundary']);
            $t->same('FileExistsError', $brokenPaths['output_folder_creation_error_class']);
            $t->contains('File exists', (string) $brokenPaths['output_folder_creation_error_message']);
            $t->same(false, $brokenPlan['chunking']['chunking_reached']);
            $t->same(false, $brokenPlan['metadata']['metadata_load_reached']);
            $t->same(0, $brokenPlan['worker_pool']['task_args_count']);
            $t->same('output-folder-create-failed', $brokenPlan['worker_pool']['pool_error_boundary']);
            $t->same(false, $brokenPlan['model_handoff']['model_handoff_reached']);
            $t->same(false, $brokenPlan['executes_python_or_models']);
            $t->same(false, $brokenPlan['executes_multiprocessing']);
            $t->same(false, $brokenPlan['executes_external_pdf_tools']);
        } finally {
            $removeTree($root);
        }
    },
];
