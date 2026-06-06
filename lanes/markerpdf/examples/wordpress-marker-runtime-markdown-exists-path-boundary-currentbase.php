<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;
use PortLibs\MarkerPDF\OutputWriter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runId = bin2hex(random_bytes(4));
$input = sys_get_temp_dir() . '/markerpdf-runtime-markdown-exists-input-' . $runId;
$output = sys_get_temp_dir() . '/markerpdf-runtime-markdown-exists-output-' . $runId;
@mkdir($input, 0777, true);
@mkdir($output, 0777, true);

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

$writePdf = static function (string $path, string $text): void {
    $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    $content = 'BT /F1 12 Tf 72 720 Td (' . $escaped . ') Tj ET';
    $pdf = "%PDF-1.4\n1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
    file_put_contents($path, $pdf);
};

try {
    $filename = 'directory-markdown.pdf';
    $writePdf($input . DIRECTORY_SEPARATOR . $filename, 'Directory markdown collision import.');
    $writePdf($input . DIRECTORY_SEPARATOR . 'ready.pdf', 'Ready runtime import.');

    $writer = new OutputWriter();
    $markdownPath = $writer->getMarkdownFilepath($output, $filename);
    @mkdir(dirname($markdownPath), 0777, true);
    if (!@mkdir($markdownPath, 0777, true) && !is_dir($markdownPath)) {
        throw new RuntimeException('Unable to create directory collision at generated Markdown path.');
    }

    $textLengthCalls = 0;
    $converterCalls = 0;
    $batch = new BatchConverter();
    $preflight = $batch->processFilePreflightPlan(
        $input . DIRECTORY_SEPARATOR . $filename,
        $output,
        ['title' => 'Directory Markdown Collision'],
        120,
        static function () use (&$textLengthCalls): int {
            $textLengthCalls++;

            return 500;
        }
    );
    $result = $batch->processFile(
        $input . DIRECTORY_SEPARATOR . $filename,
        $output,
        ['title' => 'Directory Markdown Collision'],
        120,
        static function () use (&$converterCalls): string {
            $converterCalls++;

            return '<!-- wp:paragraph --><p>Should not convert.</p><!-- /wp:paragraph -->';
        },
        static function () use (&$textLengthCalls): int {
            $textLengthCalls++;

            return 500;
        }
    );
    $plan = $batch->runtimeMainPreflightPlan($input, $output, workers: 3, torchDevice: 'cuda', torchDeviceModel: 'cpu');
    $review = $plan['worker_pool']['process_single_pdf_preflight'];
    $reviewsByFilename = [];
    foreach ($review['preflight_reviews'] as $row) {
        $reviewsByFilename[$row['filename']] = $row;
    }

    $directoryCountsAsExisting = $preflight['existing_markdown'] === true
        && $preflight['markdown_exists_path_type'] === 'directory'
        && $preflight['markdown_exists_directory_counts_as_existing'] === true
        && $preflight['skip_reason'] === 'markdown_exists';
    $blockedBeforeFiletype = $preflight['filetype_checked'] === false;
    $blockedBeforeTextLength = $preflight['text_length_checked'] === false && $textLengthCalls === 0;
    $blockedBeforeConverter = $preflight['should_invoke_converter'] === false && $converterCalls === 0;
    $workerReviewRecordsDirectory = $review['markdown_exists_path_by_filename'][$filename] === $markdownPath
        && $review['markdown_exists_path_type_by_filename'][$filename] === 'directory'
        && $review['markdown_exists_directory_filenames'] === [$filename];
    $poolDrainReturnNone = $plan['worker_pool']['pool_result_drain']['return_boundary_by_filename'][$filename] === 'markdown_exists-return-none'
        && $plan['worker_pool']['pool_result_drain']['return_value_by_filename'][$filename] === null;

    if (
        !$directoryCountsAsExisting
        || !$blockedBeforeFiletype
        || !$blockedBeforeTextLength
        || !$blockedBeforeConverter
        || !$workerReviewRecordsDirectory
        || !$poolDrainReturnNone
        || $result['status'] !== 'skipped-existing'
        || $plan['executes_python_or_models'] !== false
        || $plan['executes_multiprocessing'] !== false
        || $plan['executes_external_pdf_tools'] !== false
    ) {
        throw new RuntimeException('MarkerPDF runtime markdown_exists path-boundary smoke failed.');
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-markdown-exists-path-boundary-currentbase',
        'purpose' => 'Review convert.py process_single_pdf markdown_exists path handling when a WordPress import output has a directory collision at the generated Markdown artifact path, without launching Python, model workers, multiprocessing, or external PDF tools.',
        'source_truth' => 'sddai/markerPDF marker.output.markdown_exists uses os.path.exists(get_markdown_filepath(...)) before process_single_pdf filetype, text-length, converter, and save_markdown work.',
        'markdown_exists_path' => $preflight['markdown_exists_path'],
        'markdown_exists_function' => $preflight['markdown_exists_function'],
        'markdown_exists_path_type' => $preflight['markdown_exists_path_type'],
        'directory_markdown_counts_as_existing' => $directoryCountsAsExisting,
        'blocked_before_filetype' => $blockedBeforeFiletype,
        'blocked_before_text_length' => $blockedBeforeTextLength,
        'blocked_before_converter' => $blockedBeforeConverter,
        'worker_review_records_directory' => $workerReviewRecordsDirectory,
        'pool_drain_return_none' => $poolDrainReturnNone,
        'result_status' => $result['status'],
        'converter_calls' => $converterCalls,
        'text_length_calls' => $textLengthCalls,
        'ready_control_invoke_converter' => $reviewsByFilename['ready.pdf']['should_invoke_converter'] ?? null,
        'executes_python_or_models' => $plan['executes_python_or_models'],
        'executes_multiprocessing' => $plan['executes_multiprocessing'],
        'executes_external_pdf_tools' => $plan['executes_external_pdf_tools'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($input);
    $removeTree($output);
}
