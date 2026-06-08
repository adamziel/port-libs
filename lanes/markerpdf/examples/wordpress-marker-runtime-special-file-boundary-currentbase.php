<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runId = bin2hex(random_bytes(4));
$input = sys_get_temp_dir() . '/markerpdf-runtime-special-file-input-' . $runId;
$output = sys_get_temp_dir() . '/markerpdf-runtime-special-file-output-' . $runId;
@mkdir($input, 0777, true);
@mkdir($output, 0777, true);

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
    file_put_contents($input . DIRECTORY_SEPARATOR . 'public-report.pdf', "%PDF-1.4\n% public report\n%%EOF");
    file_put_contents($input . DIRECTORY_SEPARATOR . 'editor-notes.txt', 'WordPress sidecar queued by convert.py before worker filetype preflight.');
    mkdir($input . DIRECTORY_SEPARATOR . 'asset-folder.pdf');
    $fifoPath = $input . DIRECTORY_SEPARATOR . 'import-pipe.pdf';
    if (!posix_mkfifo($fifoPath, 0600)) {
        throw new RuntimeException('Unable to create FIFO fixture for markerPDF runtime special-file smoke.');
    }

    $plan = (new BatchConverter())->runtimeMainPreflightPlan(
        $input,
        $output,
        metadataByFilename: [
            'public-report.pdf' => ['title' => 'Public Report'],
            'import-pipe.pdf' => ['title' => 'FIFO Metadata Decoy'],
        ],
        workers: 4
    );

    $taskFilenames = array_map(
        static fn (array $taskArg): string => basename((string) $taskArg['filepath']),
        $plan['worker_pool']['task_args']
    );

    if (!in_array('import-pipe.pdf', $plan['input_listing']['fifo_basenames'], true)) {
        throw new RuntimeException('Expected FIFO upload path to be recorded as an os.path.isfile false boundary.');
    }
    if (in_array('import-pipe.pdf', $taskFilenames, true)) {
        throw new RuntimeException('FIFO upload path must not become a convert.py task arg.');
    }
    if (!in_array('public-report.pdf', $taskFilenames, true)) {
        throw new RuntimeException('Regular PDF upload must remain queued for process_single_pdf handoff.');
    }
    if (($plan['metadata']['selected_metadata_filenames'] ?? []) !== ['public-report.pdf']) {
        throw new RuntimeException('FIFO metadata decoy must not be selected by task-arg basename lookup.');
    }
    if ($plan['executes_python_or_models'] || $plan['executes_multiprocessing'] || $plan['executes_external_pdf_tools']) {
        throw new RuntimeException('Runtime special-file smoke must not launch Python, model workers, multiprocessing, or external PDF tools.');
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-special-file-boundary-currentbase',
        'purpose' => 'Review convert.py os.listdir plus os.path.isfile admission for WordPress import queues containing FIFO or directory entries named like PDFs, without launching Python, Torch, OCR, model workers, Streamlit, FastAPI, or external PDF tools.',
        'upstream_boundary' => 'convert.py files = [f for f in files if os.path.isfile(f)] before os.makedirs, chunk_files, metadata.get, model handoff, task_args, and Pool launch',
        'entry_basenames' => $plan['input_listing']['entry_basenames'],
        'file_basenames' => $plan['input_listing']['file_basenames'],
        'skipped_non_file_basenames' => $plan['input_listing']['skipped_non_file_basenames'],
        'special_file_basenames' => $plan['input_listing']['special_file_basenames'],
        'fifo_basenames' => $plan['input_listing']['fifo_basenames'],
        'task_filenames' => $taskFilenames,
        'selected_metadata_filenames' => $plan['metadata']['selected_metadata_filenames'],
        'missing_metadata_filenames' => $plan['metadata']['missing_metadata_filenames'],
        'executes_python_or_models' => $plan['executes_python_or_models'],
        'executes_multiprocessing' => $plan['executes_multiprocessing'],
        'executes_external_pdf_tools' => $plan['executes_external_pdf_tools'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    $removeTree($input);
    $removeTree($output);
}
