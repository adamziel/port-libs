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
    'plans marker_app sidebar config and convert args without executing Streamlit or models' => static function (TestRunner $t): void {
        $plan = (new MarkerRuntimePlanner())->markerAppConfigPlan([
            'languages' => ['English', 'Spanish'],
            'max_pages' => '7',
            'ocr_all_pages' => 'true',
        ]);

        $t->same('wide', $plan['page_config']['layout']);
        $t->same([0.5, 0.5], $plan['page_config']['columns']);
        $t->same('PDF file:', $plan['file_upload']['label']);
        $t->same(['pdf'], $plan['file_upload']['type']);
        $t->same(true, $plan['file_upload']['accepts_pdf_only']);
        $t->same('Languages', $plan['sidebar']['languages']['label']);
        $t->true(in_array('English', $plan['sidebar']['languages']['options'], true));
        $t->true(in_array('Spanish', $plan['sidebar']['languages']['options'], true));
        $t->same(['English', 'Spanish'], $plan['sidebar']['languages']['selected']);
        $t->same([], $plan['sidebar']['languages']['default']);
        $t->same(4, $plan['sidebar']['languages']['max_selections']);
        $t->same('Max pages to parse', $plan['sidebar']['max_pages']['label']);
        $t->same(7, $plan['sidebar']['max_pages']['value']);
        $t->same(10, $plan['sidebar']['max_pages']['default']);
        $t->same(1, $plan['sidebar']['max_pages']['min_value']);
        $t->same(true, $plan['sidebar']['ocr_all_pages']['value']);
        $t->same('Run Marker', $plan['sidebar']['run_button']['label']);
        $t->same(['langs' => ['English', 'Spanish'], 'max_pages' => 7, 'ocr_all_pages' => true], $plan['conversion_args']);
        $t->same('Page number out of {page_count}:', $plan['preview']['page_number_input']['label_template']);
        $t->same(96, $plan['preview']['page_image_dpi']);
        $t->same(true, $plan['stop_gates']['requires_uploaded_pdf']);
        $t->same(true, $plan['stop_gates']['requires_run_button']);
        $t->same('true', $plan['environment']['IN_STREAMLIT']);
        $t->same(false, $plan['executes_streamlit']);
        $t->same(false, $plan['executes_pdfium']);
        $t->same(false, $plan['executes_python_or_models']);
    },
    'rejects marker_app config values outside upstream Streamlit control bounds' => static function (TestRunner $t): void {
        $planner = new MarkerRuntimePlanner();

        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $planner->markerAppConfigPlan(['languages' => ['English', 'Spanish', 'French', 'German', 'Italian']])
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $planner->markerAppConfigPlan(['languages' => ['English', 'Not a Surya label']])
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $planner->markerAppConfigPlan(['languages' => ['English', 'English']])
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $planner->markerAppConfigPlan(['max_pages' => 0])
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $planner->markerAppConfigPlan(['ocr_all_pages' => 'sometimes'])
        );

        $defaults = $planner->markerAppConfigPlan();
        $t->same([], $defaults['conversion_args']['langs']);
        $t->same(10, $defaults['conversion_args']['max_pages']);
        $t->same(false, $defaults['conversion_args']['ocr_all_pages']);
    },
    'records convert.py import-time environment setup for batch workers' => static function (TestRunner $t): void {
        $env = (new MarkerRuntimePlanner())->conversionImportEnvironment();

        $t->same('1', $env['PYTORCH_ENABLE_MPS_FALLBACK']);
        $t->same('true', $env['IN_STREAMLIT']);
        $t->same('1', $env['PDFTEXT_CPU_WORKERS']);
    },
    'plans convert.py spawn pool task tuples and shared model handoff without executing it' => static function (TestRunner $t): void {
        $plan = (new MarkerRuntimePlanner())->convertPyMultiprocessingPlan(
            [
                [
                    'filepath' => '/srv/import/a.pdf',
                    'out_folder' => '/srv/out',
                    'metadata' => ['languages' => ['English']],
                    'min_length' => 80,
                ],
                [
                    'filepath' => '/srv/import/b.pdf',
                    'out_folder' => '/srv/out',
                    'metadata' => null,
                    'min_length' => 80,
                ],
            ],
            workers: 5,
            torchDevice: 'cuda',
            torchDeviceModel: 'cuda'
        );

        $t->same('spawn', $plan['start_method']);
        $t->same(2, $plan['total_processes']);
        $t->same('process_single_pdf', $plan['pool']['process_function']);
        $t->same('worker_init', $plan['pool']['initializer']);
        $t->same('tqdm(pool.imap(process_single_pdf, task_args))', $plan['pool']['progress_iterator']);
        $t->same(true, $plan['model_handoff']['main_load_all_models']);
        $t->same(true, $plan['model_handoff']['share_memory_before_pool']);
        $t->same('shared_model_list', $plan['model_handoff']['worker_init_argument']);
        $t->same(false, $plan['model_handoff']['mps_disables_shared_model_list']);
        $t->same('/srv/import/a.pdf', $plan['task_args'][0]['filepath']);
        $t->same(['languages' => ['English']], $plan['task_args'][0]['metadata']);
        $t->same(80, $plan['task_args'][1]['min_length']);
        $t->same(false, $plan['executes_python_or_models']);
        $t->same(false, $plan['executes_multiprocessing']);
    },
    'keeps MPS convert.py workers out of shared-memory model preload branch' => static function (TestRunner $t): void {
        $plan = (new MarkerRuntimePlanner())->convertPyMultiprocessingPlan(
            [
                [
                    'filepath' => '/srv/import/mps.pdf',
                    'out_folder' => '/srv/out',
                    'metadata' => null,
                    'min_length' => null,
                ],
            ],
            workers: 3,
            torchDevice: 'mps',
            torchDeviceModel: 'cpu'
        );

        $t->same(1, $plan['total_processes']);
        $t->same(false, $plan['model_handoff']['main_load_all_models']);
        $t->same(false, $plan['model_handoff']['share_memory_before_pool']);
        $t->same(null, $plan['model_handoff']['worker_init_argument']);
        $t->same(true, $plan['model_handoff']['worker_loads_models_when_init_arg_null']);
        $t->same(true, $plan['model_handoff']['mps_disables_shared_model_list']);
        $t->contains('Cannot use MPS with torch multiprocessing share_memory', (string) $plan['model_handoff']['warning']);
    },
    'rejects repeated convert.py spawn start method before WordPress queue planning' => static function (TestRunner $t): void {
        $t->throws(
            RuntimeException::class,
            static fn (): array => (new MarkerRuntimePlanner())->convertPyMultiprocessingPlan(
                [
                    [
                        'filepath' => '/srv/import/a.pdf',
                        'out_folder' => '/srv/out',
                        'metadata' => null,
                        'min_length' => null,
                    ],
                ],
                spawnStartMethodAlreadySet: true
            )
        );
    },
    'rejects empty project directories before WordPress worker preflight planning' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn (): array => (new MarkerRuntimePlanner())->streamlitRunPlan(''));
    },
];
