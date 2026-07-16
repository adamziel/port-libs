<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerRuntimePlanner;

return [
    'marker runtime import boundary records upstream pypdfium import order' => static function (TestRunner $t): void {
        $planner = new MarkerRuntimePlanner();
        $plan = $planner->conversionImportBoundaryPlan();

        $t->same('markerpdf.convert_import_boundary.v1', $plan['schema']);
        $t->contains('convert.py', $plan['source']);
        $t->same([
            'PYTORCH_ENABLE_MPS_FALLBACK' => '1',
            'IN_STREAMLIT' => 'true',
            'PDFTEXT_CPU_WORKERS' => '1',
        ], $plan['environment']);

        $order = $plan['import_order'];
        $pypdfiumIndex = array_search('pypdfium2', $order, true);
        $argparseIndex = array_search('argparse', $order, true);
        $torchIndex = array_search('torch.multiprocessing', $order, true);
        $loggerImportIndex = array_search('marker.logger.configure_logging', $order, true);
        $configureLoggingIndex = array_search('configure_logging', $order, true);

        $t->same(0, array_search('os', $order, true));
        $t->same(4, $pypdfiumIndex);
        $t->same(true, $pypdfiumIndex < $argparseIndex);
        $t->same(true, $pypdfiumIndex < $torchIndex);
        $t->same(true, $loggerImportIndex < $configureLoggingIndex);
        $t->same(true, $configureLoggingIndex < array_search('parse_args', $order, true));

        foreach ($plan['environment_assignments'] as $assignment) {
            $t->same('before_pypdfium2_import', $assignment['order']);
            $t->same($plan['environment'][$assignment['key']], $assignment['value']);
        }

        $t->same('pypdfium2', $plan['pypdfium_import']['module']);
        $t->contains('avoid warnings', $plan['pypdfium_import']['comment']);
        $t->same(true, $plan['pypdfium_import']['after_environment_assignments']);
        $t->same(true, $plan['pypdfium_import']['before_argparse']);
        $t->same(true, $plan['pypdfium_import']['before_torch_multiprocessing']);
        $t->same(false, $plan['pypdfium_import']['native_plan_imports_pypdfium2']);

        $t->same(true, $plan['logging']['called_at_import_time']);
        $t->same(true, $plan['logging']['before_parse_args']);
        $t->same('markerpdf.logging_plan.v1', $plan['logging']['plan']['schema']);

        $t->same(true, $plan['review_only']);
        $t->same(false, $plan['executes_python_or_models']);
        $t->same(false, $plan['executes_pypdfium']);
        $t->same(false, $plan['executes_multiprocessing']);
        $t->same(false, $plan['executes_external_pdf_tools']);
    },
];
