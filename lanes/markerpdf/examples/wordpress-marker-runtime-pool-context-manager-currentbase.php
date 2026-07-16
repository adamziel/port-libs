<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runId = bin2hex(random_bytes(4));
$input = sys_get_temp_dir() . '/markerpdf-runtime-pool-context-input-' . $runId;
$output = sys_get_temp_dir() . '/markerpdf-runtime-pool-context-output-' . $runId;
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
    foreach (['chapter-one.pdf', 'chapter-two.pdf'] as $filename) {
        file_put_contents($input . DIRECTORY_SEPARATOR . $filename, "%PDF-1.4\n% {$filename}\n%%EOF");
    }

    $batch = new BatchConverter();
    $plan = $batch->runtimeMainPreflightPlan(
        $input,
        $output,
        metadataByFilename: [
            'chapter-one.pdf' => ['title' => 'Chapter One', 'languages' => ['English']],
        ],
        workers: 4,
        torchDevice: 'cuda',
        torchDeviceModel: 'cpu'
    );
    $blocked = $batch->runtimeMainPreflightPlan(
        $input,
        $output,
        workers: 0,
        torchDevice: 'cuda',
        torchDeviceModel: 'cpu'
    );

    $context = $plan['worker_pool']['pool_context_manager'];
    $blockedContext = $blocked['worker_pool']['pool_context_manager'];

    if (
        $context['context_enter_success'] !== true
        || $context['wraps_pool_imap'] !== true
        || $context['result_drain_inside_context'] !== true
        || $context['worker_handler_override_inside_context'] !== true
        || $context['context_exit_after_worker_handler_override'] !== true
        || $context['model_list_delete_after_context_exit'] !== true
    ) {
        throw new RuntimeException('Expected convert.py Pool context manager review to wrap pool.imap before model_lst deletion.');
    }
    if (
        $blockedContext['context_enter_success'] !== false
        || $blockedContext['blocked_by'] !== 'pool-process-count-failed'
        || $blockedContext['context_exit_reached'] !== false
    ) {
        throw new RuntimeException('Expected invalid worker count to block the Pool context manager before pool.imap.');
    }
    if ($plan['executes_python_or_models'] !== false || $plan['executes_multiprocessing'] !== false || $plan['executes_external_pdf_tools'] !== false) {
        throw new RuntimeException('Runtime Pool context smoke must not execute Python models, multiprocessing, or external PDF tools.');
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-pool-context-manager-currentbase',
        'purpose' => 'Review convert.py Pool context-manager boundaries for a WordPress PDF import queue before Marker model workers are launched.',
        'selected_filenames' => $plan['chunking']['selected_filenames'],
        'context_manager_call' => $context['context_manager_call'],
        'context_enter_success' => $context['context_enter_success'],
        'processes' => $context['processes'],
        'worker_init_argument' => $context['worker_init_argument'],
        'wraps_pool_imap' => $context['wraps_pool_imap'],
        'result_drain_inside_context' => $context['result_drain_inside_context'],
        'worker_handler_override_inside_context' => $context['worker_handler_override_inside_context'],
        'context_exit_after_worker_handler_override' => $context['context_exit_after_worker_handler_override'],
        'model_list_delete_after_context_exit' => $context['model_list_delete_after_context_exit'],
        'zero_worker_context_blocked_by' => $blockedContext['blocked_by'],
        'zero_worker_context_exit_reached' => $blockedContext['context_exit_reached'],
        'executes_python_or_models' => $plan['executes_python_or_models'],
        'executes_multiprocessing' => $plan['executes_multiprocessing'],
        'executes_external_pdf_tools' => $plan['executes_external_pdf_tools'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    $removeTree($input);
    $removeTree($output);
}
