<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerRuntimePlanner;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$planner = new MarkerRuntimePlanner();
$plan = $planner->markerAppConfigPlan([
    'languages' => ['English', 'Spanish'],
    'max_pages' => 6,
    'ocr_all_pages' => true,
]);

if ($plan['executes_streamlit'] !== false || $plan['executes_pdfium'] !== false || $plan['executes_python_or_models'] !== false) {
    throw new RuntimeException('Marker app config boundary smoke must not execute upstream runtimes.');
}
if ($plan['sidebar']['languages']['max_selections'] !== 4) {
    throw new RuntimeException('Expected marker_app language multiselect to keep the upstream four-language cap.');
}
if ($plan['conversion_args'] !== ['langs' => ['English', 'Spanish'], 'max_pages' => 6, 'ocr_all_pages' => true]) {
    throw new RuntimeException('Expected marker_app config plan to produce upstream convert_pdf arguments.');
}

echo json_encode([
    'scenario' => 'wordpress-marker-runtime-app-config-boundary-currentbase',
    'purpose' => 'Record marker_app.py Streamlit app controls for a WordPress PDF import worker without launching Streamlit, PDFium, PIL, Python, or model code.',
    'layout' => $plan['page_config'],
    'file_upload' => $plan['file_upload'],
    'selected_languages' => $plan['sidebar']['languages']['selected'],
    'language_limit' => $plan['sidebar']['languages']['max_selections'],
    'max_pages' => $plan['sidebar']['max_pages'],
    'ocr_all_pages' => $plan['sidebar']['ocr_all_pages'],
    'preview' => $plan['preview'],
    'conversion_args' => $plan['conversion_args'],
    'stop_gates' => $plan['stop_gates'],
    'environment' => $plan['environment'],
    'executes_streamlit' => $plan['executes_streamlit'],
    'executes_pdfium' => $plan['executes_pdfium'],
    'executes_python_or_models' => $plan['executes_python_or_models'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
