<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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

$root = sys_get_temp_dir() . '/markerpdf-runtime-selected-file-gone-smoke-' . bin2hex(random_bytes(4));
$output = $root . DIRECTORY_SEPARATOR . 'output';
mkdir($output, 0777, true);

try {
    $queuedWordPressUpload = $root . DIRECTORY_SEPARATOR . 'wp-upload-queued-then-removed.pdf';

    $plan = (new BatchConverter())->processFilePreflightPlan(
        $queuedWordPressUpload,
        $output,
        ['title' => 'Queued WordPress upload'],
        80
    );

    if ($plan['status'] !== 'skipped-unsupported-filetype') {
        throw new RuntimeException('Expected missing selected input to stop at the upstream unsupported-filetype return boundary.');
    }
    if ($plan['worker_file_availability_boundary'] !== 'selected-input-missing-before-worker-preflight') {
        throw new RuntimeException('Expected the worker preflight to expose the selected-input disappearance boundary.');
    }
    if ($plan['should_invoke_converter'] !== false || $plan['executes_python_or_models'] !== false) {
        throw new RuntimeException('Selected-file disappearance smoke must not invoke converter or model execution.');
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-selected-file-gone-currentbase',
        'purpose' => 'Record a WordPress PDF upload that was queued by convert.py task selection but disappeared before process_single_pdf reached the optional min_length filetype gate.',
        'schema' => $plan['schema'],
        'filename' => $plan['filename'],
        'status' => $plan['status'],
        'skip_reason' => $plan['skip_reason'],
        'worker_file_availability_boundary' => $plan['worker_file_availability_boundary'],
        'filepath_path_type_at_worker_preflight' => $plan['filepath_path_type_at_worker_preflight'],
        'selected_input_missing_at_worker_preflight' => $plan['selected_input_missing_at_worker_preflight'],
        'filetype' => $plan['filetype'],
        'filetype_stdout_message_line' => $plan['filetype_review']['stdout_message_line'],
        'upstream_return_value' => $plan['upstream_return_value'],
        'upstream_return_boundary' => $plan['upstream_return_boundary'],
        'should_invoke_converter' => $plan['should_invoke_converter'],
        'executes_python_or_models' => $plan['executes_python_or_models'],
        'executes_external_pdf_tools' => $plan['executes_external_pdf_tools'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($root);
}
