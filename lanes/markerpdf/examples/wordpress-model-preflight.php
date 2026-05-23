<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\ModelPipelinePlanner;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$settings = MarkerSettings::fromEnvironment([
    'TORCH_DEVICE' => 'cuda',
    'TEXIFY_MODEL_NAME' => 'vikp/texify',
    'LAYOUT_MODEL_CHECKPOINT' => 'vikp/surya_layout3',
]);
$planner = new ModelPipelinePlanner($settings);
$plan = $planner->loadAllModelsPlan('cuda', $settings->modelDtype());
$loaders = [];

foreach ($plan['model_list_order'] as $name) {
    $model = $plan['models'][$name];
    $loaders[] = [
        'name' => $name,
        'model_loader' => $model['model_loader'],
        'processor_loader' => $model['processor_loader'],
        'model_arguments' => $model['model_arguments'],
    ];
}

echo json_encode([
    'scenario' => 'wordpress-marker-model-preflight',
    'purpose' => 'Preflight the upstream Marker model stack for a WordPress import worker without importing Python, Torch, Surya, Texify, or tabled models.',
    'environment' => $plan['environment'],
    'load_sequence' => $plan['load_sequence'],
    'model_list_order' => $plan['model_list_order'],
    'deferred_model_loaders' => $loaders,
    'flush_cuda_memory' => $planner->flushCudaMemoryPlan(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
