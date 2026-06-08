<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;
use PortLibs\MarkerPDF\OutputWriter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runId = bin2hex(random_bytes(4));
$input = sys_get_temp_dir() . '/markerpdf-runtime-markdown-symlink-input-' . $runId;
$output = sys_get_temp_dir() . '/markerpdf-runtime-markdown-symlink-output-' . $runId;
$targets = sys_get_temp_dir() . '/markerpdf-runtime-markdown-symlink-targets-' . $runId;

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
    @mkdir($input, 0777, true);
    @mkdir($output, 0777, true);
    @mkdir($targets, 0777, true);

    foreach (['live-symlink-output.pdf', 'broken-symlink-output.pdf', 'ready.pdf'] as $filename) {
        $writePdf($input . DIRECTORY_SEPARATOR . $filename, 'WordPress markdown symlink runtime import ' . $filename);
    }

    $writer = new OutputWriter();
    $liveMarkdownPath = $writer->getMarkdownFilepath($output, 'live-symlink-output.pdf');
    @mkdir(dirname($liveMarkdownPath), 0777, true);
    $liveTarget = $targets . DIRECTORY_SEPARATOR . 'already-imported.md';
    file_put_contents($liveTarget, '<!-- wp:paragraph --><p>Already imported.</p><!-- /wp:paragraph -->');
    if (!@symlink($liveTarget, $liveMarkdownPath)) {
        throw new RuntimeException('Unable to create live generated Markdown symlink fixture.');
    }

    $brokenMarkdownPath = $writer->getMarkdownFilepath($output, 'broken-symlink-output.pdf');
    @mkdir(dirname($brokenMarkdownPath), 0777, true);
    if (!@symlink($targets . DIRECTORY_SEPARATOR . 'missing-import.md', $brokenMarkdownPath)) {
        throw new RuntimeException('Unable to create broken generated Markdown symlink fixture.');
    }

    $batch = new BatchConverter();
    $plan = $batch->runtimeMainPreflightPlan($input, $output, workers: 3, torchDevice: 'cuda', torchDeviceModel: 'cpu');
    $review = $plan['worker_pool']['process_single_pdf_preflight'];
    $drain = $plan['worker_pool']['pool_result_drain'];
    $rows = [];
    foreach ($review['preflight_reviews'] as $row) {
        $rows[$row['filename']] = $row;
    }

    $liveSymlinkSkips = ($rows['live-symlink-output.pdf']['status'] ?? null) === 'skipped-existing'
        && ($rows['live-symlink-output.pdf']['markdown_exists_symlink_counts_as_existing'] ?? null) === true
        && ($rows['live-symlink-output.pdf']['should_invoke_converter'] ?? null) === false
        && ($drain['return_boundary_by_filename']['live-symlink-output.pdf'] ?? null) === 'markdown_exists-return-none';
    $brokenSymlinkContinues = ($rows['broken-symlink-output.pdf']['status'] ?? null) === 'ready-for-conversion'
        && ($rows['broken-symlink-output.pdf']['markdown_exists_broken_symlink'] ?? null) === true
        && ($rows['broken-symlink-output.pdf']['markdown_exists_broken_symlink_does_not_count_as_existing'] ?? null) === true
        && ($rows['broken-symlink-output.pdf']['should_invoke_converter'] ?? null) === true;
    $readyControlContinues = ($rows['ready.pdf']['should_invoke_converter'] ?? null) === true;
    $symlinkReviewCarried = in_array('live-symlink-output.pdf', $review['markdown_exists_symlink_filenames'], true)
        && in_array('broken-symlink-output.pdf', $review['markdown_exists_symlink_filenames'], true)
        && $review['markdown_exists_live_symlink_filenames'] === ['live-symlink-output.pdf']
        && $review['markdown_exists_broken_symlink_filenames'] === ['broken-symlink-output.pdf'];

    $result = [
        'scenario' => 'wordpress-marker-runtime-markdown-symlink-boundary-currentbase',
        'source_truth' => 'sddai/markerPDF marker.output.markdown_exists uses os.path.exists(get_markdown_filepath(...)) before convert.py process_single_pdf filetype, text-length, converter, and save_markdown work.',
        'live_markdown_symlink_path' => $liveMarkdownPath,
        'broken_markdown_symlink_path' => $brokenMarkdownPath,
        'live_symlink_skips_before_filetype' => $liveSymlinkSkips,
        'broken_symlink_does_not_count_as_existing' => $brokenSymlinkContinues,
        'ready_control_invoke_converter' => $readyControlContinues,
        'worker_review_carries_symlink_filenames' => $symlinkReviewCarried,
        'live_return_boundary' => $drain['return_boundary_by_filename']['live-symlink-output.pdf'] ?? null,
        'broken_return_boundary' => $drain['return_boundary_by_filename']['broken-symlink-output.pdf'] ?? null,
        'executes_python_or_models' => $plan['executes_python_or_models'],
        'executes_multiprocessing' => $plan['executes_multiprocessing'],
        'executes_external_pdf_tools' => $plan['executes_external_pdf_tools'],
    ];

    if (
        $result['live_symlink_skips_before_filetype'] !== true
        || $result['broken_symlink_does_not_count_as_existing'] !== true
        || $result['ready_control_invoke_converter'] !== true
        || $result['worker_review_carries_symlink_filenames'] !== true
        || $result['executes_python_or_models'] !== false
        || $result['executes_multiprocessing'] !== false
        || $result['executes_external_pdf_tools'] !== false
    ) {
        throw new RuntimeException('MarkerPDF runtime markdown symlink boundary smoke failed.');
    }

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    $removeTree($input);
    $removeTree($output);
    $removeTree($targets);
}
