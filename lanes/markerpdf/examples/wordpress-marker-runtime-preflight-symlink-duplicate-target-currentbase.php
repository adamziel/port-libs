<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runId = bin2hex(random_bytes(4));
$input = sys_get_temp_dir() . '/markerpdf-runtime-duplicate-target-input-' . $runId;
$output = sys_get_temp_dir() . '/markerpdf-runtime-duplicate-target-output-' . $runId;
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
    $originalPath = $input . DIRECTORY_SEPARATOR . 'original-report.pdf';
    $linkedPath = $input . DIRECTORY_SEPARATOR . 'linked-copy.pdf';
    $controlPath = $input . DIRECTORY_SEPARATOR . 'control.pdf';
    file_put_contents($originalPath, "%PDF-1.4\n% WordPress original import\n%%EOF");
    file_put_contents($controlPath, "%PDF-1.4\n% WordPress control import\n%%EOF");
    if (!@symlink($originalPath, $linkedPath)) {
        throw new RuntimeException('Unable to create duplicate-target symlink fixture.');
    }

    $plan = (new BatchConverter())->runtimeMainPreflightPlan(
        $input,
        $output,
        metadataByFilename: [
            'control.pdf' => ['title' => 'Control Import'],
            'original-report.pdf' => ['title' => 'Original Import', 'languages' => ['English']],
        ],
        workers: 6,
        torchDevice: 'cuda',
        torchDeviceModel: 'cpu'
    );

    $review = $plan['worker_pool']['task_arg_identity_review'];
    $taskArgsByName = [];
    foreach ($plan['worker_pool']['task_args'] as $taskArg) {
        $taskArgsByName[basename((string) $taskArg['filepath'])] = $taskArg;
    }

    if ($review['duplicate_resolved_targets_found'] !== true || $review['no_dedupe_before_task_args'] !== true) {
        throw new RuntimeException('Expected convert.py preflight to queue duplicate symlink target entries separately.');
    }
    if (
        !array_key_exists('linked-copy.pdf', $taskArgsByName)
        || !array_key_exists('metadata', $taskArgsByName['linked-copy.pdf'])
        || $taskArgsByName['linked-copy.pdf']['metadata'] !== null
    ) {
        throw new RuntimeException('Expected linked-copy metadata lookup to use the symlink basename, not the target basename.');
    }
    if (($taskArgsByName['original-report.pdf']['metadata']['title'] ?? null) !== 'Original Import') {
        throw new RuntimeException('Expected original basename metadata to remain attached to the original path.');
    }
    if ($plan['executes_python_or_models'] !== false || $plan['executes_multiprocessing'] !== false) {
        throw new RuntimeException('Duplicate-target runtime preflight smoke must not launch Python workers or models.');
    }
    if ($plan['executes_external_pdf_tools'] !== false) {
        throw new RuntimeException('Duplicate-target runtime preflight smoke must not execute external PDF tools.');
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-preflight-symlink-duplicate-target-currentbase',
        'purpose' => 'Review convert.py task tuple construction when a WordPress import queue contains a regular PDF and a symlink to the same PDF target, without deduping or executing Marker model workers.',
        'selected_filenames' => $plan['chunking']['selected_filenames'],
        'duplicate_resolved_targets_found' => $review['duplicate_resolved_targets_found'],
        'duplicate_resolved_target_filenames' => $review['duplicate_resolved_target_filenames'],
        'duplicate_resolved_target_group_count' => $review['duplicate_resolved_target_group_count'],
        'no_dedupe_before_task_args' => $review['no_dedupe_before_task_args'],
        'metadata_lookup' => $review['metadata_lookup'],
        'metadata_lookup_uses_entry_basename' => $review['metadata_lookup_uses_entry_basename'],
        'target_basename_metadata_fallback' => $review['target_basename_metadata_fallback'],
        'original_metadata_title' => $taskArgsByName['original-report.pdf']['metadata']['title'] ?? null,
        'linked_copy_metadata' => $taskArgsByName['linked-copy.pdf']['metadata'] ?? null,
        'linked_copy_path_preserved' => ($taskArgsByName['linked-copy.pdf']['filepath'] ?? null) === $linkedPath,
        'executes_python_or_models' => $plan['executes_python_or_models'],
        'executes_multiprocessing' => $plan['executes_multiprocessing'],
        'executes_external_pdf_tools' => $plan['executes_external_pdf_tools'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($input);
    $removeTree($output);
}
