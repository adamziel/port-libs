<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;
use PortLibs\MarkerPDF\MarkerRuntimePlanner;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$runId = bin2hex(random_bytes(4));
$input = sys_get_temp_dir() . '/markerpdf-runtime-mps-case-input-' . $runId;
$output = sys_get_temp_dir() . '/markerpdf-runtime-mps-case-output-' . $runId;
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
    file_put_contents($input . DIRECTORY_SEPARATOR . 'case-boundary.pdf', "%PDF-1.4\n% mps case boundary\n%%EOF");

    $batch = new BatchConverter();
    $uppercasePlan = $batch->runtimeMainPreflightPlan(
        $input,
        $output,
        workers: 3,
        torchDevice: 'MPS',
        torchDeviceModel: 'CPU',
        modelSlots: ['layout-detector', 'ocr-recognizer']
    );
    $lowercasePlan = $batch->runtimeMainPreflightPlan(
        $input,
        $output,
        workers: 3,
        torchDevice: 'mps',
        torchDeviceModel: 'CPU',
        modelSlots: ['layout-detector', 'ocr-recognizer']
    );
    $spacedPlan = $batch->runtimeMainPreflightPlan(
        $input,
        $output,
        workers: 3,
        torchDevice: ' mps ',
        torchDeviceModel: 'CPU',
        modelSlots: ['layout-detector', 'ocr-recognizer']
    );

    $planner = new MarkerRuntimePlanner();
    $standaloneUppercase = $planner->convertPyMultiprocessingPlan(
        [
            [
                'filepath' => $input . DIRECTORY_SEPARATOR . 'case-boundary.pdf',
                'out_folder' => $output,
                'metadata' => ['source' => 'wordpress-upload'],
                'min_length' => null,
            ],
        ],
        workers: 3,
        torchDevice: 'MPS',
        torchDeviceModel: 'CPU'
    );

    if (
        $uppercasePlan['model_handoff']['uses_mps_no_shared_memory_branch'] !== false
        || $uppercasePlan['model_handoff']['main_load_all_models'] !== true
        || $uppercasePlan['worker_pool']['worker_initializer']['parent_shared_model_reused'] !== true
        || $spacedPlan['model_handoff']['uses_mps_no_shared_memory_branch'] !== false
        || $spacedPlan['model_handoff']['main_load_all_models'] !== true
        || $lowercasePlan['model_handoff']['uses_mps_no_shared_memory_branch'] !== true
        || $lowercasePlan['worker_pool']['worker_initializer']['loads_models_in_worker'] !== true
        || $standaloneUppercase['model_handoff']['worker_loads_models_when_init_arg_null'] !== false
    ) {
        throw new RuntimeException('Expected only exact lowercase mps to disable shared-memory model handoff.');
    }
    if (
        $uppercasePlan['executes_python_or_models'] !== false
        || $uppercasePlan['executes_multiprocessing'] !== false
        || $uppercasePlan['executes_external_pdf_tools'] !== false
        || $standaloneUppercase['executes_python_or_models'] !== false
        || $standaloneUppercase['executes_multiprocessing'] !== false
    ) {
        throw new RuntimeException('Runtime MPS case boundary smoke must not execute Python, models, multiprocessing, or external PDF tools.');
    }

    echo json_encode([
        'scenario' => 'wordpress-marker-runtime-mps-case-boundary-currentbase',
        'purpose' => 'Review convert.py exact lowercase MPS device boundary before WordPress PDF batch imports launch Marker workers.',
        'source_truth' => 'sddai/markerPDF convert.py branches only when TORCH_DEVICE == "mps" or TORCH_DEVICE_MODEL == "mps".',
        'uppercase_torch_device' => $uppercasePlan['model_handoff']['torch_device'],
        'uppercase_uses_mps_no_shared_memory_branch' => $uppercasePlan['model_handoff']['uses_mps_no_shared_memory_branch'],
        'uppercase_main_load_all_models' => $uppercasePlan['model_handoff']['main_load_all_models'],
        'uppercase_share_memory_before_pool' => $uppercasePlan['model_handoff']['share_memory_before_pool'],
        'uppercase_parent_shared_model_reused' => $uppercasePlan['worker_pool']['worker_initializer']['parent_shared_model_reused'],
        'uppercase_worker_loads_models_when_init_arg_null' => $standaloneUppercase['model_handoff']['worker_loads_models_when_init_arg_null'],
        'spaced_torch_device' => $spacedPlan['model_handoff']['torch_device'],
        'spaced_uses_mps_no_shared_memory_branch' => $spacedPlan['model_handoff']['uses_mps_no_shared_memory_branch'],
        'spaced_main_load_all_models' => $spacedPlan['model_handoff']['main_load_all_models'],
        'lowercase_torch_device' => $lowercasePlan['model_handoff']['torch_device'],
        'lowercase_uses_mps_no_shared_memory_branch' => $lowercasePlan['model_handoff']['uses_mps_no_shared_memory_branch'],
        'lowercase_main_load_all_models' => $lowercasePlan['model_handoff']['main_load_all_models'],
        'lowercase_worker_loads_models_when_init_arg_null' => $lowercasePlan['model_handoff']['worker_loads_models_when_init_arg_null'],
        'executes_python_or_models' => $uppercasePlan['executes_python_or_models'],
        'executes_multiprocessing' => $uppercasePlan['executes_multiprocessing'],
        'executes_external_pdf_tools' => $uppercasePlan['executes_external_pdf_tools'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    $removeTree($input);
    $removeTree($output);
}
