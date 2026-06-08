<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runId = bin2hex(random_bytes(4));
$input = sys_get_temp_dir() . '/markerpdf-model-list-arity-input-' . $runId;
$output = sys_get_temp_dir() . '/markerpdf-model-list-arity-output-' . $runId;
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
    file_put_contents($input . DIRECTORY_SEPARATOR . 'partial-models.pdf', "%PDF-1.4\n% partial model list\n%%EOF");

    $plan = (new BatchConverter())->runtimeMainPreflightPlan(
        $input,
        $output,
        workers: 2,
        torchDevice: 'cuda',
        torchDeviceModel: 'cpu',
        modelSlots: [
            'texify',
            'layout',
            'order',
            'detection',
            'ocr',
        ]
    );

    $handoff = $plan['model_handoff'];
    $shareMemory = $handoff['model_share_memory_review'];
    $initializer = $plan['worker_pool']['worker_initializer'];

    if (
        $handoff['model_list_arity_error_boundary'] !== 'convert-single-pdf-model-unpack-failed'
        || $handoff['model_list_arity_error_message'] !== 'not enough values to unpack (expected 6, got 5)'
        || $handoff['model_list_arity_not_checked_before_pool_launch'] !== true
        || $plan['worker_pool']['pool_launchable'] !== true
        || $initializer['process_single_pdf_catches_downstream_unpack_error'] !== true
    ) {
        throw new RuntimeException('Expected model-list arity boundary to be deferred to convert_single_pdf and caught by process_single_pdf.');
    }
    if (
        $plan['executes_python_or_models'] !== false
        || $plan['executes_multiprocessing'] !== false
        || $plan['executes_external_pdf_tools'] !== false
    ) {
        throw new RuntimeException('Model-list arity smoke must not execute Python, models, multiprocessing, or external PDF tools.');
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-model-list-arity-currentbase',
        'purpose' => 'Review convert.py model-list arity boundaries for a WordPress PDF import queue without launching Marker model workers.',
        'source_truth' => 'sddai/markerPDF marker.convert.convert_single_pdf unpacks model_lst into six slots after worker_init assigns model_refs.',
        'selected_filenames' => $plan['chunking']['selected_filenames'],
        'model_handoff_success' => $handoff['model_handoff_success'],
        'model_slot_expected_count' => $handoff['model_slot_expected_count'],
        'model_slot_count' => $handoff['model_slot_count'],
        'model_list_arity_matches_convert_single_pdf_unpack' => $handoff['model_list_arity_matches_convert_single_pdf_unpack'],
        'model_list_arity_error_boundary' => $handoff['model_list_arity_error_boundary'],
        'model_list_arity_error_class' => $handoff['model_list_arity_error_class'],
        'model_list_arity_error_message' => $handoff['model_list_arity_error_message'],
        'model_list_arity_not_checked_before_pool_launch' => $handoff['model_list_arity_not_checked_before_pool_launch'],
        'share_memory_call_count' => $shareMemory['share_memory_call_count'],
        'share_memory_error_found' => $shareMemory['share_memory_error_found'],
        'console_summary_reached' => $plan['console_summary']['summary_reached'],
        'task_args_count' => $plan['worker_pool']['task_args_count'],
        'pool_launchable' => $plan['worker_pool']['pool_launchable'],
        'worker_initializer_reached' => $initializer['initializer_reached'],
        'downstream_convert_single_pdf_model_unpack_boundary' => $initializer['downstream_convert_single_pdf_model_unpack_boundary'],
        'process_single_pdf_catches_downstream_unpack_error' => $initializer['process_single_pdf_catches_downstream_unpack_error'],
        'executes_python_or_models' => $plan['executes_python_or_models'],
        'executes_multiprocessing' => $plan['executes_multiprocessing'],
        'executes_external_pdf_tools' => $plan['executes_external_pdf_tools'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    $removeTree($input);
    $removeTree($output);
}
