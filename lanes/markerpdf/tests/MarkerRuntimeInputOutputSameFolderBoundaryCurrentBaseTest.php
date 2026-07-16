<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;
use PortLibs\MarkerPDF\OutputWriter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-runtime-same-folder-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerPDF same-folder runtime folder.');
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
        throw new RuntimeException('Unable to inspect temporary markerPDF same-folder directory.');
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
    'records convert.py same input output folder admission before task args' => static function (
        TestRunner $t
    ) use ($makeTempDir, $removeTree, $runtimeDirectoryOrder): void {
        $folder = $makeTempDir();
        try {
            foreach (['already-imported.pdf', 'ready.pdf', 'upload-notes.txt'] as $filename) {
                file_put_contents($folder . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% {$filename}\n%%EOF");
            }
            (new OutputWriter())->saveMarkdown(
                $folder,
                'already-imported.pdf',
                '<!-- wp:paragraph --><p>Previously imported from same folder.</p><!-- /wp:paragraph -->',
                [],
                ['title' => 'Same Folder Existing Import']
            );

            $entryOrder = $runtimeDirectoryOrder($folder);
            $fileOrder = $runtimeDirectoryOrder($folder, filesOnly: true);

            $plan = (new BatchConverter())->runtimeMainPreflightPlan(
                $folder,
                $folder,
                metadataByFilename: [
                    'ready.pdf' => ['title' => 'Ready Same Folder Import'],
                    'already-imported.pdf' => ['title' => 'Existing Same Folder Import'],
                ],
                workers: 5,
                torchDevice: 'cuda',
                torchDeviceModel: 'cpu'
            );

            $resolution = $plan['paths']['path_resolution'];
            $review = $resolution['input_output_same_folder_review'];
            $t->same('convert.py same input/output folder runtime preflight', $review['source']);
            $t->same(true, $review['review_reached']);
            $t->same(true, $resolution['input_output_same_folder']);
            $t->same(true, $review['input_output_same_folder']);
            $t->same('os.listdir(in_folder) before os.makedirs(out_folder, exist_ok=True)', $review['ordering']);
            $t->same(true, $review['listdir_runs_before_makedirs']);
            $t->same(true, $review['makedirs_exist_ok_directory_noop']);
            $t->same(true, $review['no_same_folder_runtime_guard']);
            $t->same(true, $review['task_args_out_folder_equals_input_folder']);
            $t->same(true, $review['existing_input_files_remain_task_candidates']);
            $t->same(true, $review['same_folder_output_artifact_directories_filtered_only_by_isfile']);
            $t->same(false, $review['native_plan_creates_output_folder']);
            $t->same(false, $review['executes_python_or_models']);
            $t->same(false, $review['executes_multiprocessing']);
            $t->same(false, $review['executes_external_pdf_tools']);

            $t->same($entryOrder, $plan['input_listing']['entry_basenames']);
            $t->same($fileOrder, $plan['input_listing']['file_basenames']);
            $t->same(['already-imported'], $plan['input_listing']['skipped_non_file_basenames']);
            $t->same(['upload-notes.txt'], $plan['input_listing']['selected_non_pdf_filenames']);
            $t->same($fileOrder, $plan['chunking']['selected_filenames']);
            $t->same(false, $plan['paths']['output_folder_creation_required']);
            $t->same(false, $plan['paths']['output_folder_creation_blocked']);
            $t->same(3, $plan['worker_pool']['task_args_count']);
            $t->same(3, $plan['worker_pool']['total_processes']);
            $t->same(true, $plan['worker_pool']['pool_launchable']);

            $taskArgsByName = [];
            foreach ($plan['worker_pool']['task_args'] as $taskArg) {
                $taskArgsByName[basename((string) $taskArg['filepath'])] = $taskArg;
            }
            $t->same($folder, $taskArgsByName['already-imported.pdf']['out_folder']);
            $t->same($folder, $taskArgsByName['ready.pdf']['out_folder']);
            $t->same($folder, $taskArgsByName['upload-notes.txt']['out_folder']);
            $t->same(['title' => 'Existing Same Folder Import'], $taskArgsByName['already-imported.pdf']['metadata']);
            $t->same(['title' => 'Ready Same Folder Import'], $taskArgsByName['ready.pdf']['metadata']);
            $t->same(null, $taskArgsByName['upload-notes.txt']['metadata']);

            $workerReview = $plan['worker_pool']['process_single_pdf_preflight'];
            $t->same(true, $workerReview['review_reached']);
            $t->same(['upload-notes.txt'], $workerReview['selected_non_pdf_filenames']);
            $t->same(true, $workerReview['sidecar_reaches_task_args_before_preflight']);
            $t->same(['already-imported.pdf'], $workerReview['existing_markdown_filenames']);
            $t->same('skipped-existing', $workerReview['status_by_filename']['already-imported.pdf']);
            $t->same('ready-for-conversion', $workerReview['status_by_filename']['ready.pdf']);
            $t->same('ready-for-conversion', $workerReview['status_by_filename']['upload-notes.txt']);
            $t->same(false, $plan['executes_python_or_models']);
            $t->same(false, $plan['executes_multiprocessing']);
            $t->same(false, $plan['executes_external_pdf_tools']);
        } finally {
            $removeTree($folder);
        }
    },
];
