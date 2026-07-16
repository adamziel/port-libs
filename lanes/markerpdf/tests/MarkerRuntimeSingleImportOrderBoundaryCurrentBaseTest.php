<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerRuntimePlanner;

return [
    'records convert_single.py import order before single upload runtime parsing' => static function (TestRunner $t): void {
        $planner = new MarkerRuntimePlanner();
        $plan = $planner->singleDocumentImportBoundaryPlan();

        $t->same('markerpdf.convert_single_import_boundary.v1', $plan['schema']);
        $t->contains('convert_single.py', $plan['source']);
        $t->same(['PYTORCH_ENABLE_MPS_FALLBACK' => '1'], $plan['environment']);

        $order = $plan['import_order'];
        $timeIndex = array_search('time', $order, true);
        $pypdfiumIndex = array_search('pypdfium2', $order, true);
        $osIndex = array_search('os', $order, true);
        $envIndex = array_search('set_PYTORCH_ENABLE_MPS_FALLBACK', $order, true);
        $argparseIndex = array_search('argparse', $order, true);
        $loadModelsIndex = array_search('marker.models.load_all_models', $order, true);
        $saveMarkdownIndex = array_search('marker.output.save_markdown', $order, true);
        $configureLoggingIndex = array_search('configure_logging', $order, true);
        $parseArgsIndex = array_search('parse_args', $order, true);

        $t->same(0, $timeIndex);
        $t->same(1, $pypdfiumIndex);
        $t->same(2, $osIndex);
        $t->same(3, $envIndex);
        $t->same(true, $pypdfiumIndex < $argparseIndex);
        $t->same(true, $pypdfiumIndex < $loadModelsIndex);
        $t->same(true, $envIndex > $pypdfiumIndex);
        $t->same(true, $envIndex < $argparseIndex);
        $t->same(true, $configureLoggingIndex < $parseArgsIndex);
        $t->same(true, $saveMarkdownIndex < $configureLoggingIndex);

        $environmentAssignment = $plan['environment_assignments'][0];
        $t->same('PYTORCH_ENABLE_MPS_FALLBACK', $environmentAssignment['key']);
        $t->same('1', $environmentAssignment['value']);
        $t->same('after_pypdfium2_import_before_argparse', $environmentAssignment['order']);
        $t->same('os.environ["PYTORCH_ENABLE_MPS_FALLBACK"] = "1"', $environmentAssignment['statement']);

        $t->same('pypdfium2', $plan['pypdfium_import']['module']);
        $t->same('import pypdfium2', $plan['pypdfium_import']['statement']);
        $t->contains('avoid warnings', $plan['pypdfium_import']['comment']);
        $t->same(false, $plan['pypdfium_import']['after_environment_assignments']);
        $t->same(true, $plan['pypdfium_import']['before_argparse']);
        $t->same(true, $plan['pypdfium_import']['before_marker_model_imports']);
        $t->same(false, $plan['pypdfium_import']['native_plan_imports_pypdfium2']);

        $t->same('configure_logging', $plan['logging']['function']);
        $t->same(true, $plan['logging']['called_at_import_time']);
        $t->same(true, $plan['logging']['before_parse_args']);
        $t->same('markerpdf.logging_plan.v1', $plan['logging']['plan']['schema']);

        $t->same(false, $plan['runtime_boundaries']['filesystem_touched_by_import_plan']);
        $t->same(false, $plan['runtime_boundaries']['model_handoff_reached_by_import_plan']);
        $t->same(false, $plan['runtime_boundaries']['single_pdf_conversion_reached_by_import_plan']);
        $t->same(true, $plan['runtime_boundaries']['argument_parser_reached_after_logging']);
        $t->same(true, $plan['review_only']);
        $t->same(false, $plan['executes_python_or_models']);
        $t->same(false, $plan['executes_pypdfium']);
        $t->same(false, $plan['executes_multiprocessing']);
        $t->same(false, $plan['executes_streamlit']);
        $t->same(false, $plan['executes_fastapi']);
        $t->same(false, $plan['executes_external_pdf_tools']);
    },
];
