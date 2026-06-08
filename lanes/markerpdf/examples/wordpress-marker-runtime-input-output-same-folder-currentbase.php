<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;
use PortLibs\MarkerPDF\OutputWriter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runId = bin2hex(random_bytes(4));
$folder = sys_get_temp_dir() . '/markerpdf-runtime-same-folder-smoke-' . $runId;
if (!mkdir($folder, 0777, true) && !is_dir($folder)) {
    throw new RuntimeException('Unable to create same-folder runtime smoke directory.');
}

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

try {
    foreach (['already-imported.pdf', 'ready.pdf', 'upload-notes.txt'] as $filename) {
        file_put_contents($folder . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% {$filename}\n%%EOF");
    }

    (new OutputWriter())->saveMarkdown(
        $folder,
        'already-imported.pdf',
        '<!-- wp:paragraph --><p>Previously imported from the upload folder.</p><!-- /wp:paragraph -->',
        [],
        ['title' => 'Same Folder Existing Import']
    );

    $plan = (new BatchConverter())->runtimeMainPreflightPlan(
        $folder,
        $folder,
        metadataByFilename: [
            'already-imported.pdf' => ['title' => 'Existing Same Folder Import'],
            'ready.pdf' => ['title' => 'Ready Same Folder Import'],
        ],
        workers: 5,
        torchDevice: 'cuda',
        torchDeviceModel: 'cpu'
    );

    $review = $plan['paths']['path_resolution']['input_output_same_folder_review'];
    $workerReview = $plan['worker_pool']['process_single_pdf_preflight'];
    $result = [
        'scenario' => 'wordpress-marker-runtime-input-output-same-folder-currentbase',
        'input_output_same_folder' => $review['input_output_same_folder'],
        'listdir_runs_before_makedirs' => $review['listdir_runs_before_makedirs'],
        'makedirs_exist_ok_directory_noop' => $review['makedirs_exist_ok_directory_noop'],
        'no_same_folder_runtime_guard' => $review['no_same_folder_runtime_guard'],
        'task_args_out_folder_equals_input_folder' => $review['task_args_out_folder_equals_input_folder'],
        'selected_filenames' => $plan['chunking']['selected_filenames'],
        'skipped_non_file_basenames' => $plan['input_listing']['skipped_non_file_basenames'],
        'selected_non_pdf_filenames' => $plan['input_listing']['selected_non_pdf_filenames'],
        'existing_markdown_filenames' => $workerReview['existing_markdown_filenames'],
        'task_args_count' => $plan['worker_pool']['task_args_count'],
        'pool_launchable' => $plan['worker_pool']['pool_launchable'],
        'executes_python_or_models' => $plan['executes_python_or_models'],
        'executes_multiprocessing' => $plan['executes_multiprocessing'],
        'executes_external_pdf_tools' => $plan['executes_external_pdf_tools'],
    ];

    if (
        $result['input_output_same_folder'] !== true
        || $result['listdir_runs_before_makedirs'] !== true
        || $result['makedirs_exist_ok_directory_noop'] !== true
        || $result['task_args_out_folder_equals_input_folder'] !== true
        || $result['existing_markdown_filenames'] !== ['already-imported.pdf']
        || !in_array('upload-notes.txt', $result['selected_filenames'], true)
        || $result['executes_python_or_models'] !== false
        || $result['executes_multiprocessing'] !== false
        || $result['executes_external_pdf_tools'] !== false
    ) {
        throw new RuntimeException('MarkerPDF same-folder runtime preflight smoke failed.');
    }

    echo '<!-- markerpdf-runtime-same-folder-preflight-currentbase-smoke ' . htmlspecialchars(json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES) . " -->\n";
    echo "<h2>MarkerPDF Runtime Same-Folder Preflight</h2>\n";
    echo "<p>Same input/output folders are recorded as review-only runtime metadata before model workers launch.</p>\n";
    echo '<!-- markerpdf:runtime-same-folder-preflight ' . htmlspecialchars(json_encode([
        'source' => 'sddai/markerPDF convert.py os.listdir + os.makedirs + task_args',
        'review_only' => true,
        'dependency_closure' => 'No new support component; native PHP filesystem and runtime preflight planner only.',
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES) . " -->\n";
} finally {
    $removeTree($folder);
}
