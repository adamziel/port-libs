<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;
use PortLibs\MarkerPDF\OutputWriter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runId = bin2hex(random_bytes(4));
$root = sys_get_temp_dir() . '/markerpdf-runtime-worker-availability-smoke-' . $runId;
$output = $root . DIRECTORY_SEPARATOR . 'output';
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
    $missing = $root . DIRECTORY_SEPARATOR . 'selected-then-missing.pdf';
    $directory = $root . DIRECTORY_SEPARATOR . 'selected-then-directory.pdf';
    mkdir($directory);
    $broken = $root . DIRECTORY_SEPARATOR . 'selected-then-broken.pdf';
    if (!@symlink($root . DIRECTORY_SEPARATOR . 'missing-target.pdf', $broken)) {
        throw new RuntimeException('Unable to create worker availability broken symlink fixture.');
    }

    $batch = new BatchConverter();
    $missingNoMin = $batch->processFilePreflightPlan(
        $missing,
        $output,
        ['title' => 'WordPress upload disappeared after task construction'],
        null
    );
    $missingWithMin = $batch->processFilePreflightPlan(
        $missing,
        $output,
        ['title' => 'WordPress upload disappeared after task construction'],
        80
    );
    $directoryNoMin = $batch->processFilePreflightPlan(
        $directory,
        $output,
        ['title' => 'WordPress upload became a directory after task construction'],
        null
    );
    $brokenWithMin = $batch->processFilePreflightPlan(
        $broken,
        $output,
        ['title' => 'WordPress upload symlink broke after task construction'],
        80
    );

    (new OutputWriter())->saveMarkdown(
        $output,
        'selected-then-missing.pdf',
        '<!-- wp:paragraph --><p>Already imported.</p><!-- /wp:paragraph -->',
        [],
        ['title' => 'Already Imported']
    );
    $missingExisting = $batch->processFilePreflightPlan(
        $missing,
        $output,
        ['title' => 'Already imported before worker path access'],
        80
    );

    $result = [
        'scenario' => 'wordpress-marker-runtime-worker-availability-boundary-currentbase',
        'source' => 'sddai/markerPDF convert.py::process_single_pdf worker path availability boundary',
        'purpose' => 'Review WordPress upload paths that disappear or change type after convert.py task construction but before process_single_pdf reaches filetype or conversion.',
        'missing_without_min_length_status' => $missingNoMin['status'],
        'missing_without_min_length_stage' => $missingNoMin['worker_file_availability_runtime_boundary']['handling_stage'],
        'missing_without_min_length_reaches_converter' => $missingNoMin['worker_file_availability_runtime_boundary']['unavailable_input_reaches_converter'],
        'missing_without_min_length_return_boundary_if_unavailable' => $missingNoMin['worker_file_availability_runtime_boundary']['upstream_return_boundary_if_unavailable'],
        'missing_with_min_length_status' => $missingWithMin['status'],
        'missing_with_min_length_stage' => $missingWithMin['worker_file_availability_runtime_boundary']['handling_stage'],
        'missing_with_min_length_return_boundary' => $missingWithMin['worker_file_availability_runtime_boundary']['upstream_return_boundary_if_unavailable'],
        'directory_without_min_length_stage' => $directoryNoMin['worker_file_availability_runtime_boundary']['handling_stage'],
        'directory_without_min_length_reaches_converter' => $directoryNoMin['worker_file_availability_runtime_boundary']['unavailable_input_reaches_converter'],
        'broken_with_min_length_stage' => $brokenWithMin['worker_file_availability_runtime_boundary']['handling_stage'],
        'broken_with_min_length_return_boundary' => $brokenWithMin['worker_file_availability_runtime_boundary']['upstream_return_boundary_if_unavailable'],
        'existing_markdown_stage' => $missingExisting['worker_file_availability_runtime_boundary']['handling_stage'],
        'existing_markdown_status' => $missingExisting['status'],
        'executes_python_or_models' => $missingNoMin['executes_python_or_models']
            || $missingWithMin['executes_python_or_models']
            || $directoryNoMin['executes_python_or_models']
            || $brokenWithMin['executes_python_or_models']
            || $missingExisting['executes_python_or_models'],
        'executes_external_pdf_tools' => $missingNoMin['executes_external_pdf_tools']
            || $missingWithMin['executes_external_pdf_tools']
            || $directoryNoMin['executes_external_pdf_tools']
            || $brokenWithMin['executes_external_pdf_tools']
            || $missingExisting['executes_external_pdf_tools'],
    ];

    if (
        $result['missing_without_min_length_status'] !== 'ready-for-conversion'
        || $result['missing_without_min_length_stage'] !== 'convert_single_pdf'
        || $result['missing_without_min_length_reaches_converter'] !== true
        || $result['missing_without_min_length_return_boundary_if_unavailable'] !== 'conversion-exception-print-return-none'
        || $result['missing_with_min_length_status'] !== 'skipped-unsupported-filetype'
        || $result['missing_with_min_length_stage'] !== 'find_filetype'
        || $result['missing_with_min_length_return_boundary'] !== 'unsupported-filetype-return-zero'
        || $result['directory_without_min_length_stage'] !== 'convert_single_pdf'
        || $result['directory_without_min_length_reaches_converter'] !== true
        || $result['broken_with_min_length_stage'] !== 'find_filetype'
        || $result['broken_with_min_length_return_boundary'] !== 'unsupported-filetype-return-zero'
        || $result['existing_markdown_stage'] !== 'markdown_exists'
        || $result['existing_markdown_status'] !== 'skipped-existing'
        || $result['executes_python_or_models'] !== false
        || $result['executes_external_pdf_tools'] !== false
    ) {
        throw new RuntimeException('MarkerPDF worker availability preflight smoke failed.');
    }

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($root);
}
