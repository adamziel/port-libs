<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-runtime-metadata-dash-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf metadata dash boundary folder.');
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
        throw new RuntimeException('Unable to inspect runtime metadata dash boundary directory order.');
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
    'opens dash metadata_file as a literal cwd file instead of stdin before model handoff' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree, $runtimeDirectoryOrder): void {
        $root = $makeTempDir();
        $previousCwd = getcwd();
        if (!is_string($previousCwd)) {
            throw new RuntimeException('Unable to capture cwd for markerPDF dash metadata boundary test.');
        }

        try {
            $input = $root . DIRECTORY_SEPARATOR . 'uploads';
            $output = $root . DIRECTORY_SEPARATOR . 'marker-output';
            mkdir($input);
            mkdir($output);

            foreach (['dash.pdf', 'other.pdf'] as $filename) {
                file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% {$filename}\n%%EOF");
            }
            mkdir($input . DIRECTORY_SEPARATOR . '-');
            file_put_contents($output . DIRECTORY_SEPARATOR . '-', '{"other.pdf": {"title": "Output Decoy",}');

            $dashMetadata = $root . DIRECTORY_SEPARATOR . '-';
            file_put_contents($dashMetadata, json_encode([
                'dash.pdf' => ['title' => 'Dash Metadata Import', 'languages' => ['English']],
            ], JSON_THROW_ON_ERROR));

            if (!chdir($root)) {
                throw new RuntimeException('Unable to enter markerPDF dash metadata fixture root.');
            }

            $batch = new BatchConverter();
            $argumentPlan = $batch->runtimeMainArgumentPreflightPlan([
                $input,
                $output,
                '--metadata_file',
                '-',
            ]);
            $runtimePlan = $batch->runtimeMainPreflightPlan(
                $input,
                $output,
                workers: 4,
                metadataFile: '-',
                torchDevice: 'cuda',
                torchDeviceModel: 'cpu'
            );

            $fileOrder = $runtimeDirectoryOrder($input, filesOnly: true);
            $selectedMetadataFilenames = array_values(array_filter(
                $fileOrder,
                static fn (string $filename): bool => $filename === 'dash.pdf'
            ));
            $missingMetadataFilenames = array_values(array_filter(
                $fileOrder,
                static fn (string $filename): bool => $filename !== 'dash.pdf'
            ));

            $t->same(true, $argumentPlan['parse_args']['parse_args_success']);
            $t->same('-', $argumentPlan['arguments']['options']['metadata_file']);
            $t->same(true, $argumentPlan['semantic_boundaries']['metadata_file_truthy_for_json_load']);
            $t->same(false, $argumentPlan['semantic_boundaries']['empty_metadata_file_skips_json_load']);

            $metadata = $runtimePlan['metadata'];
            $t->same('-', $metadata['metadata_file_input']);
            $t->same($dashMetadata, $metadata['metadata_file']);
            $t->same('os.path.abspath(args.metadata_file)', $metadata['metadata_file_abspath_call']);
            $t->same('process_cwd', $metadata['metadata_file_abspath_base']);
            $t->same($root, $metadata['metadata_file_process_cwd']);
            $t->same(true, $metadata['metadata_file_relative_to_process_cwd']);
            $t->same(false, $metadata['metadata_file_relative_to_input_folder']);
            $t->same(false, $metadata['metadata_file_relative_to_output_folder']);
            $t->same(true, $metadata['metadata_file_is_dash_literal']);
            $t->same($dashMetadata, $metadata['metadata_file_dash_path']);
            $t->same(false, $metadata['metadata_file_dash_treated_as_stdin']);
            $t->same(false, $metadata['metadata_file_stdin_read']);
            $t->same(true, $metadata['metadata_file_open_uses_filesystem_path']);
            $t->same(true, $metadata['metadata_file_path_exists']);
            $t->same('file', $metadata['metadata_file_path_type']);
            $t->same('open(metadata_file, "r")', $metadata['metadata_file_open_call']);
            $t->same($input . DIRECTORY_SEPARATOR . '-', $metadata['metadata_file_input_folder_candidate']);
            $t->same($output . DIRECTORY_SEPARATOR . '-', $metadata['metadata_file_output_folder_candidate']);
            $t->same(true, $metadata['metadata_file_input_folder_candidate_exists']);
            $t->same(true, $metadata['metadata_file_output_folder_candidate_exists']);
            $t->same(true, $metadata['metadata_load_success']);
            $t->same(['dash.pdf'], $metadata['metadata_filenames']);
            $t->same($selectedMetadataFilenames, $metadata['selected_metadata_filenames']);
            $t->same($missingMetadataFilenames, $metadata['missing_metadata_filenames']);
            $t->same($fileOrder, $runtimePlan['chunking']['selected_filenames']);
            $t->same(false, in_array('-', $runtimePlan['chunking']['selected_filenames'], true));

            $taskArgsByName = [];
            foreach ($runtimePlan['worker_pool']['task_args'] as $taskArg) {
                $taskArgsByName[basename($taskArg['filepath'])] = $taskArg;
            }
            $t->same(['title' => 'Dash Metadata Import', 'languages' => ['English']], $taskArgsByName['dash.pdf']['metadata']);
            $t->same(null, $taskArgsByName['other.pdf']['metadata']);
            $t->same(2, $runtimePlan['worker_pool']['task_args_count']);
            $t->same(2, $runtimePlan['worker_pool']['total_processes']);
            $t->same(true, $runtimePlan['worker_pool']['pool_launchable']);
            $t->same(false, $runtimePlan['executes_python_or_models']);
            $t->same(false, $runtimePlan['executes_multiprocessing']);
            $t->same(false, $runtimePlan['executes_external_pdf_tools']);
        } finally {
            chdir($previousCwd);
            $removeTree($root);
        }
    },
];
