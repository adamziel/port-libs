<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-runtime-special-file-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf runtime special-file folder.');
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
        throw new RuntimeException('Unable to inspect runtime special-file directory order.');
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
    'excludes os path isfile false special files before chunking metadata and task args' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree, $runtimeDirectoryOrder): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        try {
            file_put_contents($input . DIRECTORY_SEPARATOR . 'alpha.pdf', "%PDF-1.4\n% alpha\n%%EOF");
            file_put_contents($input . DIRECTORY_SEPARATOR . 'beta.pdf', "%PDF-1.4\n% beta\n%%EOF");
            mkdir($input . DIRECTORY_SEPARATOR . 'folder-upload.pdf');

            $fifoPath = $input . DIRECTORY_SEPARATOR . 'pipe-upload.pdf';
            if (!posix_mkfifo($fifoPath, 0600)) {
                throw new RuntimeException('Unable to create FIFO fixture for markerPDF runtime preflight.');
            }

            $entryOrder = $runtimeDirectoryOrder($input);
            $fileOrder = $runtimeDirectoryOrder($input, filesOnly: true);
            $skippedOrder = array_values(array_filter(
                $entryOrder,
                static fn (string $basename): bool => !in_array($basename, $fileOrder, true)
            ));

            $plan = (new BatchConverter())->runtimeMainPreflightPlan(
                $input,
                $output,
                metadataByFilename: [
                    'alpha.pdf' => ['title' => 'Alpha Import'],
                    'pipe-upload.pdf' => ['title' => 'FIFO Metadata Decoy'],
                ],
                workers: 5
            );

            $listing = $plan['input_listing'];
            $recordsByName = [];
            foreach ($listing['skipped_non_file_records'] as $record) {
                $recordsByName[$record['basename']] = $record;
            }
            $taskNames = array_map(
                static fn (array $taskArg): string => basename((string) $taskArg['filepath']),
                $plan['worker_pool']['task_args']
            );

            $t->same('os.listdir + os.path.isfile', $listing['source']);
            $t->same('os.listdir filesystem order', $listing['entry_order_source']);
            $t->same(false, $listing['sort_applied_before_chunking']);
            $t->same(true, $listing['preserves_os_listdir_order']);
            $t->same($entryOrder, $listing['entry_basenames']);
            $t->same($fileOrder, $listing['file_basenames']);
            $t->same($skippedOrder, $listing['skipped_non_file_basenames']);
            $t->same(2, $listing['skipped_non_file_count']);
            $t->same(['pipe-upload.pdf'], $listing['special_file_basenames']);
            $t->same(['pipe-upload.pdf'], $listing['fifo_basenames']);
            $t->same(true, isset($recordsByName['pipe-upload.pdf']));
            $t->same('fifo', $recordsByName['pipe-upload.pdf']['path_type']);
            $t->same(false, $recordsByName['pipe-upload.pdf']['os_path_isfile']);
            $t->same(false, $recordsByName['pipe-upload.pdf']['task_candidate']);
            $t->same(false, $recordsByName['pipe-upload.pdf']['is_symlink']);
            $t->same('directory', $recordsByName['folder-upload.pdf']['path_type']);
            $t->same(false, $recordsByName['folder-upload.pdf']['task_candidate']);

            $t->same(false, in_array('pipe-upload.pdf', $plan['chunking']['selected_filenames'], true));
            $t->same($fileOrder, $plan['chunking']['selected_filenames']);
            $t->same(count($fileOrder), $plan['chunking']['selected_count']);
            $t->same(count($fileOrder), $plan['worker_pool']['task_args_count']);
            $t->same(count($fileOrder), $plan['worker_pool']['total_processes']);
            $t->same(false, in_array('pipe-upload.pdf', $taskNames, true));
            $t->same($fileOrder, $taskNames);
            $t->same(['alpha.pdf', 'pipe-upload.pdf'], $plan['metadata']['metadata_filenames']);
            $t->same(['alpha.pdf'], $plan['metadata']['selected_metadata_filenames']);
            $t->same(['beta.pdf'], $plan['metadata']['missing_metadata_filenames']);
            $t->same(false, in_array('pipe-upload.pdf', $plan['metadata']['selected_metadata_filenames'], true));
            $t->same(false, $plan['executes_python_or_models']);
            $t->same(false, $plan['executes_multiprocessing']);
            $t->same(false, $plan['executes_external_pdf_tools']);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
];
