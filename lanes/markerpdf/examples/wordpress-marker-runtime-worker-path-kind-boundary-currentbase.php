<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runId = bin2hex(random_bytes(4));
$root = sys_get_temp_dir() . '/markerpdf-runtime-worker-path-kind-smoke-' . $runId;
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
    $target = $root . DIRECTORY_SEPARATOR . 'wp-upload-target.pdf';
    $queuedLink = $root . DIRECTORY_SEPARATOR . 'wp-upload-linked-directory.pdf';
    $brokenLink = $root . DIRECTORY_SEPARATOR . 'wp-upload-broken-link.pdf';

    file_put_contents($target, "%PDF-1.4\n% queued upload before target replacement\n%%EOF");
    if (!@symlink($target, $queuedLink)) {
        throw new RuntimeException('Unable to create queued symlink smoke fixture.');
    }
    unlink($target);
    mkdir($target);

    if (!@symlink($root . DIRECTORY_SEPARATOR . 'missing-upload-target.pdf', $brokenLink)) {
        throw new RuntimeException('Unable to create broken symlink smoke fixture.');
    }

    $batch = new BatchConverter();
    $directory = $batch->processFilePreflightPlan(
        $queuedLink,
        $output,
        ['title' => 'WordPress symlink target changed to directory'],
        80
    );
    $broken = $batch->processFilePreflightPlan(
        $brokenLink,
        $output,
        ['title' => 'WordPress symlink target missing'],
        80
    );

    $result = [
        'scenario' => 'wordpress-marker-runtime-worker-path-kind-boundary-currentbase',
        'purpose' => 'Review queued WordPress upload paths whose symlink targets stop being ordinary files before process_single_pdf reaches the optional min_length filetype gate.',
        'directory_symlink_boundary' => $directory['worker_file_availability_boundary'],
        'directory_symlink_path_type' => $directory['filepath_path_type_at_worker_preflight'],
        'directory_symlink_classified' => $directory['selected_input_directory_at_worker_preflight'],
        'directory_symlink_rejected_before_converter' => $directory['status'] === 'skipped-unsupported-filetype'
            && $directory['upstream_return_value'] === 0
            && $directory['should_invoke_converter'] === false,
        'directory_symlink_filetype_stdout' => $directory['filetype_review']['stdout_message_line'],
        'broken_symlink_boundary' => $broken['worker_file_availability_boundary'],
        'broken_symlink_path_type' => $broken['filepath_path_type_at_worker_preflight'],
        'broken_symlink_rejected_before_converter' => $broken['status'] === 'skipped-unsupported-filetype'
            && $broken['upstream_return_value'] === 0
            && $broken['should_invoke_converter'] === false,
        'executes_python_or_models' => $directory['executes_python_or_models'] || $broken['executes_python_or_models'],
        'executes_external_pdf_tools' => $directory['executes_external_pdf_tools'] || $broken['executes_external_pdf_tools'],
    ];

    if (
        $result['directory_symlink_boundary'] !== 'selected-input-not-file-before-worker-preflight'
        || $result['directory_symlink_path_type'] !== 'directory'
        || $result['directory_symlink_classified'] !== true
        || $result['directory_symlink_rejected_before_converter'] !== true
        || $result['broken_symlink_boundary'] !== 'selected-input-broken-symlink-before-worker-preflight'
        || $result['broken_symlink_path_type'] !== 'broken-symlink'
        || $result['broken_symlink_rejected_before_converter'] !== true
        || $result['executes_python_or_models'] !== false
        || $result['executes_external_pdf_tools'] !== false
    ) {
        throw new RuntimeException('MarkerPDF worker path-kind preflight smoke failed.');
    }

    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($root);
}
