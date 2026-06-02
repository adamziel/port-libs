<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerRuntimePlanner;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$planner = new MarkerRuntimePlanner();
$tasks = [
    [
        'filepath' => '/wp/uploads/pdf-import/annual-report.pdf',
        'out_folder' => '/wp/uploads/marker-output',
        'metadata' => ['title' => 'Annual Report', 'languages' => ['English']],
        'min_length' => 250,
    ],
    [
        'filepath' => '/wp/uploads/pdf-import/editor-checklist.pdf',
        'out_folder' => '/wp/uploads/marker-output',
        'metadata' => ['title' => 'Editor Checklist'],
        'min_length' => 250,
    ],
];

$sharedPlan = $planner->convertPyMultiprocessingPlan($tasks, workers: 5, torchDevice: 'cuda', torchDeviceModel: 'cuda');
$mpsPlan = $planner->convertPyMultiprocessingPlan([$tasks[0]], workers: 5, torchDevice: 'mps', torchDeviceModel: 'cpu');

if ($sharedPlan['executes_multiprocessing'] !== false || $sharedPlan['executes_python_or_models'] !== false) {
    throw new RuntimeException('Runtime conversion boundary smoke must not execute upstream workers.');
}
if ($sharedPlan['model_handoff']['share_memory_before_pool'] !== true) {
    throw new RuntimeException('Expected CPU/CUDA conversion plan to share loaded models before the pool.');
}
if ($mpsPlan['model_handoff']['worker_init_argument'] !== null || $mpsPlan['model_handoff']['mps_disables_shared_model_list'] !== true) {
    throw new RuntimeException('Expected MPS conversion plan to avoid shared model list handoff.');
}

echo json_encode([
    'scenario' => 'wordpress-marker-runtime-conversion-boundary-currentbase',
    'purpose' => 'Record convert.py multiprocessing and model-handoff decisions for a WordPress PDF import queue without launching Python, Torch, pdftext, pypdfium, or model workers.',
    'environment' => $sharedPlan['environment'],
    'start_method' => $sharedPlan['start_method'],
    'total_processes' => $sharedPlan['total_processes'],
    'task_count' => count($sharedPlan['task_args']),
    'first_task' => $sharedPlan['task_args'][0],
    'pool' => $sharedPlan['pool'],
    'shared_model_handoff' => $sharedPlan['model_handoff'],
    'mps_model_handoff' => $mpsPlan['model_handoff'],
    'executes_python_or_models' => $sharedPlan['executes_python_or_models'],
    'executes_multiprocessing' => $sharedPlan['executes_multiprocessing'],
    'executes_external_pdf_tools' => $sharedPlan['executes_external_pdf_tools'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
