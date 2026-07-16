<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerRuntimePlanner;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$planner = new MarkerRuntimePlanner();
$runPlan = $planner->streamlitRunPlan('/opt/markerPDF', [
    'PATH' => '/usr/local/bin:/usr/bin',
    'PDFTEXT_CPU_WORKERS' => '6',
]);

echo json_encode([
    'scenario' => 'wordpress-marker-runtime-preflight',
    'purpose' => 'Record the upstream Marker Streamlit/logger runtime boundary for a WordPress import worker without launching Streamlit, FastAPI, Python, or model code.',
    'streamlit_command' => $runPlan['command'],
    'environment_overlay' => [
        'IN_STREAMLIT' => $runPlan['environment']['IN_STREAMLIT'],
        'PDFTEXT_CPU_WORKERS' => $runPlan['environment']['PDFTEXT_CPU_WORKERS'],
    ],
    'marker_app_import_environment' => $planner->markerAppImportEnvironment(),
    'logging' => $planner->loggingPlan(),
    'executes_subprocess' => $runPlan['executes_subprocess'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
