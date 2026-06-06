<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerRuntimePlanner;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$planner = new MarkerRuntimePlanner();
$plan = $planner->conversionImportBoundaryPlan();
$order = $plan['import_order'];

$pypdfiumIndex = array_search('pypdfium2', $order, true);
$argparseIndex = array_search('argparse', $order, true);
$torchIndex = array_search('torch.multiprocessing', $order, true);
$configureLoggingIndex = array_search('configure_logging', $order, true);
$parseArgsIndex = array_search('parse_args', $order, true);

if ($pypdfiumIndex === false || $argparseIndex === false || $torchIndex === false) {
    throw new RuntimeException('Runtime import boundary is missing a required upstream import marker.');
}

if (!($pypdfiumIndex < $argparseIndex && $pypdfiumIndex < $torchIndex)) {
    throw new RuntimeException('pypdfium2 must stay before argparse and Torch runtime imports.');
}

if (!($configureLoggingIndex !== false && $parseArgsIndex !== false && $configureLoggingIndex < $parseArgsIndex)) {
    throw new RuntimeException('configure_logging must be recorded before runtime argument parsing.');
}

$summary = [
    'scenario' => 'wordpress-marker-runtime-import-order-boundary-currentbase',
    'schema' => $plan['schema'],
    'environment' => $plan['environment'],
    'pypdfium_before_argparse' => true,
    'pypdfium_before_torch' => true,
    'configure_logging_before_parse_args' => true,
    'review_only' => $plan['review_only'],
    'executes_python_or_models' => $plan['executes_python_or_models'],
    'executes_pypdfium' => $plan['executes_pypdfium'],
    'executes_external_pdf_tools' => $plan['executes_external_pdf_tools'],
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
