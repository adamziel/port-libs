<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerRuntimePlanner;

return [
    'plans upstream logger levels and future warning suppression' => static function (TestRunner $t): void {
        $plan = (new MarkerRuntimePlanner())->loggingPlan();

        $t->same('WARNING', $plan['root_level']);
        $t->same('ERROR', $plan['logger_levels']['pdfminer']);
        $t->same('ERROR', $plan['logger_levels']['PIL']);
        $t->same('ERROR', $plan['logger_levels']['fitz']);
        $t->same('ERROR', $plan['logger_levels']['ocrmypdf']);
        $t->same([['action' => 'ignore', 'category' => 'FutureWarning']], $plan['warning_filters']);
    },
    'plans run_marker_app streamlit command and environment overlay without executing it' => static function (TestRunner $t): void {
        $planner = new MarkerRuntimePlanner();
        $plan = $planner->streamlitRunPlan('/srv/markerPDF/', [
            'PATH' => '/usr/bin',
            'IN_STREAMLIT' => 'false',
            'PDFTEXT_CPU_WORKERS' => 8,
            'KEEP_FALSEY' => false,
            'DROP_NULL' => null,
        ]);

        $t->same(['streamlit', 'run', '/srv/markerPDF/marker_app.py'], $plan['command']);
        $t->same('/usr/bin', $plan['environment']['PATH']);
        $t->same('true', $plan['environment']['IN_STREAMLIT']);
        $t->same('1', $plan['environment']['PDFTEXT_CPU_WORKERS']);
        $t->same('0', $plan['environment']['KEEP_FALSEY']);
        $t->true(!array_key_exists('DROP_NULL', $plan['environment']));
        $t->same(false, $plan['executes_subprocess']);
    },
    'records marker app import-time environment setup' => static function (TestRunner $t): void {
        $env = (new MarkerRuntimePlanner())->markerAppImportEnvironment();

        $t->same('1', $env['PYTORCH_ENABLE_MPS_FALLBACK']);
        $t->same('true', $env['IN_STREAMLIT']);
        $t->same('1', $env['PDFTEXT_CPU_WORKERS']);
    },
    'rejects empty project directories before WordPress worker preflight planning' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn (): array => (new MarkerRuntimePlanner())->streamlitRunPlan(''));
    },
];
