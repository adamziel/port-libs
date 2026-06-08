<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-runtime-tilde-path-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerPDF tilde-path boundary folder.');
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
        throw new RuntimeException('Unable to inspect temporary markerPDF tilde-path directory order.');
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
    'keeps leading tilde input output and metadata paths literal under process cwd' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree, $runtimeDirectoryOrder): void {
        $root = $makeTempDir();
        $previousCwd = getcwd();
        if (!is_string($previousCwd)) {
            throw new RuntimeException('Unable to capture cwd for markerPDF tilde-path boundary test.');
        }

        try {
            $tildeRoot = $root . DIRECTORY_SEPARATOR . '~';
            $input = $tildeRoot . DIRECTORY_SEPARATOR . 'uploads';
            $output = $tildeRoot . DIRECTORY_SEPARATOR . 'marker-output';
            mkdir($input, 0777, true);
            mkdir($output, 0777, true);

            foreach (['tilde-report.pdf', 'tilde-sidecar.txt'] as $filename) {
                file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% {$filename}\n%%EOF");
            }
            $metadataPath = $tildeRoot . DIRECTORY_SEPARATOR . 'metadata.json';
            file_put_contents($metadataPath, json_encode([
                'tilde-report.pdf' => [
                    'title' => 'Literal Tilde Import',
                    'languages' => ['English'],
                ],
            ], JSON_THROW_ON_ERROR));

            if (!chdir($root)) {
                throw new RuntimeException('Unable to enter markerPDF tilde-path fixture root.');
            }

            $batch = new BatchConverter();
            $argumentPlan = $batch->runtimeMainArgumentPreflightPlan([
                '~/uploads',
                '~/marker-output',
                '--metadata_file',
                '~/metadata.json',
            ]);
            $plan = $batch->runtimeMainPreflightPlan(
                '~/uploads',
                '~/marker-output',
                workers: 4,
                metadataFile: '~/metadata.json',
                torchDevice: 'cuda',
                torchDeviceModel: 'cpu'
            );

            $fileOrder = $runtimeDirectoryOrder($input, filesOnly: true);

            $t->same(true, $argumentPlan['parse_args']['parse_args_success']);
            $t->same('~/uploads', $argumentPlan['arguments']['in_folder']);
            $t->same('~/marker-output', $argumentPlan['arguments']['out_folder']);
            $t->same('~/metadata.json', $argumentPlan['arguments']['options']['metadata_file']);
            $t->same('abspath_input_output', $argumentPlan['next_stage']);

            $resolution = $plan['paths']['path_resolution'];
            $t->same('convert.py os.path.abspath input/output boundary', $resolution['source']);
            $t->same('~/uploads', $resolution['input_folder_argument']);
            $t->same('~/marker-output', $resolution['output_folder_argument']);
            $t->same($input, $resolution['absolute_input_folder']);
            $t->same($output, $resolution['absolute_output_folder']);
            $t->same($root, $resolution['process_cwd']);
            $t->same(true, $resolution['input_folder_has_leading_tilde']);
            $t->same(true, $resolution['output_folder_has_leading_tilde']);
            $t->same(false, $resolution['input_folder_tilde_expanded_to_home']);
            $t->same(false, $resolution['output_folder_tilde_expanded_to_home']);
            $t->same($tildeRoot . DIRECTORY_SEPARATOR . 'uploads', $resolution['input_folder_literal_tilde_path']);
            $t->same($tildeRoot . DIRECTORY_SEPARATOR . 'marker-output', $resolution['output_folder_literal_tilde_path']);
            $t->same(true, $resolution['literal_tilde_segment_preserved']);
            $t->same(false, $resolution['filesystem_touched_by_abspath']);

            $metadata = $plan['metadata'];
            $t->same('~/metadata.json', $metadata['metadata_file_input']);
            $t->same($metadataPath, $metadata['metadata_file']);
            $t->same(true, $metadata['metadata_file_has_leading_tilde']);
            $t->same(false, $metadata['metadata_file_tilde_expanded_to_home']);
            $t->same($metadataPath, $metadata['metadata_file_literal_tilde_path']);
            $t->same(true, $metadata['metadata_file_relative_to_process_cwd']);
            $t->same(false, $metadata['metadata_file_relative_to_input_folder']);
            $t->same(false, $metadata['metadata_file_relative_to_output_folder']);
            $t->same(true, $metadata['metadata_file_path_exists']);
            $t->same('file', $metadata['metadata_file_path_type']);
            $t->same(true, $metadata['metadata_load_success']);
            $t->same(['tilde-report.pdf'], $metadata['metadata_filenames']);
            $t->same(['tilde-report.pdf'], $metadata['selected_metadata_filenames']);
            $t->same(['tilde-sidecar.txt'], $metadata['missing_metadata_filenames']);

            $t->same($fileOrder, $plan['input_listing']['file_basenames']);
            $t->same($fileOrder, $plan['chunking']['selected_filenames']);
            $t->same(2, $plan['worker_pool']['task_args_count']);
            $t->same(2, $plan['worker_pool']['total_processes']);
            $t->same(true, $plan['worker_pool']['pool_launchable']);

            $taskArgsByName = [];
            foreach ($plan['worker_pool']['task_args'] as $taskArg) {
                $taskArgsByName[basename((string) $taskArg['filepath'])] = $taskArg;
            }
            $t->same($input . DIRECTORY_SEPARATOR . 'tilde-report.pdf', $taskArgsByName['tilde-report.pdf']['filepath']);
            $t->same($output, $taskArgsByName['tilde-report.pdf']['out_folder']);
            $t->same(['title' => 'Literal Tilde Import', 'languages' => ['English']], $taskArgsByName['tilde-report.pdf']['metadata']);
            $t->same(null, $taskArgsByName['tilde-sidecar.txt']['metadata']);
            $t->same(false, $plan['executes_python_or_models']);
            $t->same(false, $plan['executes_multiprocessing']);
            $t->same(false, $plan['executes_external_pdf_tools']);
        } finally {
            chdir($previousCwd);
            $removeTree($root);
        }
    },
];
