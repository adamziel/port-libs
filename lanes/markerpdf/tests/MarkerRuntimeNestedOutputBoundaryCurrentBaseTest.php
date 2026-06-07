<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-runtime-nested-output-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf nested-output boundary folder.');
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

$runtimeDirectoryOrder = static function (string $path, bool $filesOnly = false): array {
    $handle = opendir($path);
    if ($handle === false) {
        throw new RuntimeException('Unable to inspect temporary nested-output directory order.');
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
    'records missing nested output folders as created after input listing and excluded from current task args' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree, $runtimeDirectoryOrder): void {
        $root = $makeTempDir();
        try {
            $input = $root . DIRECTORY_SEPARATOR . 'uploads';
            mkdir($input);
            foreach (['alpha.pdf', 'beta.pdf'] as $filename) {
                file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% {$filename}\n%%EOF");
            }
            $expectedFileOrder = $runtimeDirectoryOrder($input, filesOnly: true);
            $output = $input . DIRECTORY_SEPARATOR . 'marker-output';

            $plan = (new BatchConverter())->runtimeMainPreflightPlan(
                $input,
                $output,
                workers: 4,
                metadataByFilename: [
                    'alpha.pdf' => ['title' => 'Alpha Import'],
                    'marker-output' => ['title' => 'Output Directory Decoy'],
                ]
            );

            $resolution = $plan['paths']['path_resolution'];
            $t->same(false, $resolution['input_folder_relative_to_output_folder'] ?? null);
            $t->same(true, $resolution['output_folder_relative_to_input_folder'] ?? null);
            $t->same(true, $resolution['output_folder_nested_in_input_folder'] ?? null);
            $t->same(false, $resolution['output_folder_existed_before_input_listing'] ?? null);
            $t->same(true, $resolution['output_folder_creation_after_input_listing_required'] ?? null);
            $t->same(true, $resolution['nested_output_folder_created_after_listing_not_task_candidate'] ?? null);
            $t->same(false, $resolution['output_folder_task_candidate_before_creation'] ?? null);

            $t->same(false, is_dir($output));
            $t->same(true, $plan['paths']['output_folder_creation_required']);
            $t->same(false, $plan['paths']['native_plan_creates_output_folder']);
            $t->same('after_list_input_files_before_chunk_files', $plan['paths']['output_folder_creation_order']);
            $t->same($expectedFileOrder, $plan['input_listing']['file_basenames']);
            $t->same(false, in_array('marker-output', $plan['input_listing']['entry_basenames'], true));
            $t->same(false, in_array('marker-output', $plan['chunking']['selected_filenames'], true));
            $t->same($expectedFileOrder, $plan['chunking']['selected_filenames']);
            $t->same(['alpha.pdf'], $plan['metadata']['selected_metadata_filenames']);
            $t->same(['beta.pdf'], $plan['metadata']['missing_metadata_filenames']);
            $t->same(2, $plan['worker_pool']['task_args_count']);
            $t->same(2, $plan['worker_pool']['total_processes']);
            $t->same(true, $plan['worker_pool']['pool_launchable']);
            $t->same(false, $plan['executes_python_or_models']);
            $t->same(false, $plan['executes_multiprocessing']);
            $t->same(false, $plan['executes_external_pdf_tools']);
        } finally {
            $removeTree($root);
        }
    },
    'records pre-existing nested output directories as skipped non-file entries before task args' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree, $runtimeDirectoryOrder): void {
        $root = $makeTempDir();
        try {
            $input = $root . DIRECTORY_SEPARATOR . 'uploads';
            $output = $input . DIRECTORY_SEPARATOR . 'marker-output';
            mkdir($output, 0777, true);
            foreach (['queued.pdf', 'sidecar.txt'] as $filename) {
                file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% {$filename}\n%%EOF");
            }

            $expectedEntryOrder = $runtimeDirectoryOrder($input);
            $expectedFileOrder = $runtimeDirectoryOrder($input, filesOnly: true);
            $plan = (new BatchConverter())->runtimeMainPreflightPlan($input, $output, workers: 4);
            $resolution = $plan['paths']['path_resolution'];

            $t->same(true, $resolution['output_folder_relative_to_input_folder'] ?? null);
            $t->same(true, $resolution['output_folder_nested_in_input_folder'] ?? null);
            $t->same(true, $resolution['output_folder_existed_before_input_listing'] ?? null);
            $t->same(false, $resolution['output_folder_creation_after_input_listing_required'] ?? null);
            $t->same(false, $resolution['nested_output_folder_created_after_listing_not_task_candidate'] ?? null);
            $t->same(false, $resolution['output_folder_task_candidate_before_creation'] ?? null);

            $t->same($expectedEntryOrder, $plan['input_listing']['entry_basenames']);
            $t->same($expectedFileOrder, $plan['input_listing']['file_basenames']);
            $t->same(true, in_array('marker-output', $plan['input_listing']['skipped_non_file_basenames'], true));
            $t->same(false, in_array('marker-output', $plan['chunking']['selected_filenames'], true));
            $t->same(['sidecar.txt'], $plan['input_listing']['selected_non_pdf_filenames']);
            $t->same(false, $plan['paths']['output_folder_creation_required']);
            $t->same(2, $plan['worker_pool']['task_args_count']);
            $t->same(false, $plan['executes_python_or_models']);
            $t->same(false, $plan['executes_external_pdf_tools']);
        } finally {
            $removeTree($root);
        }
    },
];
