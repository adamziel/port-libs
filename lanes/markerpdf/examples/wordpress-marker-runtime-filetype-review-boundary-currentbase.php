<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;
use PortLibs\MarkerPDF\FiletypeDetector;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runId = bin2hex(random_bytes(4));
$input = sys_get_temp_dir() . '/markerpdf-runtime-filetype-review-input-' . $runId;
$output = sys_get_temp_dir() . '/markerpdf-runtime-filetype-review-output-' . $runId;
mkdir($input, 0777, true);
mkdir($output, 0777, true);

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
    $empty = $input . DIRECTORY_SEPARATOR . 'empty-upload.pdf';
    $png = $input . DIRECTORY_SEPARATOR . 'image-upload.pdf';
    $ready = $input . DIRECTORY_SEPARATOR . 'ready.pdf';
    file_put_contents($empty, '');
    file_put_contents($png, "\x89PNG\r\n\x1a\nnot a marker-supported pdf");
    file_put_contents($ready, "%PDF-1.4\n% WordPress upload with searchable native text\n%%EOF");

    $detector = new FiletypeDetector();
    $emptyReview = $detector->findFiletypeReview($empty);
    $pngReview = $detector->findFiletypeReview($png);
    $readyReview = $detector->findFiletypeReview($ready);

    $batch = new BatchConverter();
    $runtime = $batch->runtimeMainPreflightPlan(
        $input,
        $output,
        minLength: -1,
        workers: 3,
        torchDevice: 'cuda',
        torchDeviceModel: 'cpu'
    );
    $workerReview = $runtime['worker_pool']['process_single_pdf_preflight'];

    $stdoutFilenames = $workerReview['filetype_stdout_filenames'];
    sort($stdoutFilenames, SORT_STRING);

    if (
        $emptyReview['stdout_message_line'] !== 'Could not determine filetype for ' . $empty
        || $pngReview['stdout_message_line'] !== 'Found nonstandard filetype image/png'
        || $readyReview['stdout_message_line'] !== null
        || $workerReview['status_by_filename']['empty-upload.pdf'] !== 'skipped-unsupported-filetype'
        || $workerReview['status_by_filename']['image-upload.pdf'] !== 'skipped-unsupported-filetype'
        || $workerReview['status_by_filename']['ready.pdf'] !== 'ready-for-conversion'
        || $stdoutFilenames !== ['empty-upload.pdf', 'image-upload.pdf']
        || $runtime['executes_python_or_models'] !== false
        || $runtime['executes_multiprocessing'] !== false
        || $runtime['executes_external_pdf_tools'] !== false
    ) {
        throw new RuntimeException('Expected runtime filetype diagnostics to be review-only before WordPress import conversion.');
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-filetype-review-boundary-currentbase',
        'source' => 'sddai/markerPDF convert.py::process_single_pdf + marker.pdf.utils.find_filetype',
        'purpose' => 'Expose unsupported WordPress upload filetype diagnostics before Marker model handoff.',
        'empty_upload_status' => $workerReview['status_by_filename']['empty-upload.pdf'],
        'empty_upload_stdout' => $workerReview['filetype_stdout_message_by_filename']['empty-upload.pdf'],
        'empty_upload_return_boundary' => $workerReview['filetype_review_by_filename']['empty-upload.pdf']['return_boundary'],
        'image_upload_status' => $workerReview['status_by_filename']['image-upload.pdf'],
        'image_upload_mimetype' => $workerReview['filetype_review_by_filename']['image-upload.pdf']['mimetype'],
        'image_upload_stdout' => $workerReview['filetype_stdout_message_by_filename']['image-upload.pdf'],
        'image_upload_return_boundary' => $workerReview['filetype_review_by_filename']['image-upload.pdf']['return_boundary'],
        'ready_pdf_status' => $workerReview['status_by_filename']['ready.pdf'],
        'ready_pdf_stdout' => $workerReview['filetype_stdout_message_by_filename']['ready.pdf'],
        'filetype_stdout_filenames' => $stdoutFilenames,
        'unsupported_uploads_block_converter' => true,
        'executes_python_or_models' => $runtime['executes_python_or_models'],
        'executes_multiprocessing' => $runtime['executes_multiprocessing'],
        'executes_external_pdf_tools' => $runtime['executes_external_pdf_tools'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($input);
    $removeTree($output);
}
