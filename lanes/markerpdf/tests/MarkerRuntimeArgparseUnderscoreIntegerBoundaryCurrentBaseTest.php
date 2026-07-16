<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;
use PortLibs\MarkerPDF\SingleDocumentConverter;

return [
    'accepts Python int underscore separators for convert.py runtime options' => static function (TestRunner $t): void {
        $batch = new BatchConverter();

        $plan = $batch->runtimeMainArgumentPreflightPlan([
            '--chunk_idx',
            '+1_2',
            '--num_chunks=2_0',
            '--max',
            '-3_0',
            '--workers',
            '1_0',
            '--min_length=8_0',
            '/wp/uploads/marker-batch',
            '/wp/marker-output',
        ]);
        $invalidDouble = $batch->runtimeMainArgumentPreflightPlan([
            '/wp/uploads/marker-batch',
            '/wp/marker-output',
            '--workers',
            '1__0',
        ]);
        $invalidTrailing = $batch->runtimeMainArgumentPreflightPlan([
            '/wp/uploads/marker-batch',
            '/wp/marker-output',
            '--min_length=10_',
        ]);

        $t->same(true, $plan['parse_args']['parse_args_success']);
        $t->same([
            'chunk_idx' => 12,
            'num_chunks' => 20,
            'max' => -30,
            'workers' => 10,
            'metadata_file' => null,
            'min_length' => 80,
        ], $plan['arguments']['options']);
        $t->same(false, $plan['arguments']['defaults_applied']['chunk_idx']);
        $t->same(false, $plan['arguments']['defaults_applied']['num_chunks']);
        $t->same(false, $plan['arguments']['defaults_applied']['max']);
        $t->same(false, $plan['arguments']['defaults_applied']['workers']);
        $t->same(false, $plan['arguments']['defaults_applied']['min_length']);
        $t->same(true, $plan['semantic_boundaries']['negative_max_allowed_by_argparse']);
        $t->same(false, $plan['semantic_boundaries']['workers_less_than_one_deferred_to_pool_creation']);
        $t->same(false, $plan['semantic_boundaries']['num_chunks_less_than_one_deferred_to_chunk_files']);
        $t->same(false, $plan['parse_args']['filesystem_touched_before_error']);
        $t->same('abspath_input_output', $plan['next_stage']);
        $t->same(false, $plan['executes_python_or_models']);
        $t->same(false, $plan['executes_multiprocessing']);
        $t->same(false, $plan['executes_external_pdf_tools']);

        $history = $plan['arguments']['option_value_history'];
        $t->same(5, count($history));
        $t->same(12, $history[0]['value']);
        $t->same(20, $history[1]['value']);
        $t->same(-30, $history[2]['value']);
        $t->same(10, $history[3]['value']);
        $t->same(80, $history[4]['value']);

        $t->same(false, $invalidDouble['parse_args']['parse_args_success']);
        $t->same('--workers', $invalidDouble['parse_args']['error_argument']);
        $t->same("argument --workers: invalid int value: '1__0'", $invalidDouble['parse_args']['error_message']);
        $t->same(false, $invalidDouble['parse_args']['filesystem_touched_before_error']);
        $t->same(false, $invalidTrailing['parse_args']['parse_args_success']);
        $t->same('--min_length', $invalidTrailing['parse_args']['error_argument']);
        $t->same("argument --min_length: invalid int value: '10_'", $invalidTrailing['parse_args']['error_message']);
        $t->same(false, $invalidTrailing['executes_python_or_models']);
    },
    'accepts Python int underscore separators for convert_single.py runtime options' => static function (TestRunner $t): void {
        $single = new SingleDocumentConverter();

        $plan = $single->runtimeArgumentPreflightPlan([
            '--max_pages',
            '1_0',
            '--start_page=-2_0',
            '--batch_multiplier',
            '+3_2',
            '--langs',
            'English,French',
            '/wp/uploads/editorial-checklist.pdf',
            '/wp/marker-output',
        ]);
        $invalidDouble = $single->runtimeArgumentPreflightPlan([
            '/wp/uploads/editorial-checklist.pdf',
            '/wp/marker-output',
            '--max_pages',
            '1__0',
        ]);
        $invalidTrailing = $single->runtimeArgumentPreflightPlan([
            '/wp/uploads/editorial-checklist.pdf',
            '/wp/marker-output',
            '--batch_multiplier=10_',
        ]);

        $t->same(true, $plan['parse_args']['parse_args_success']);
        $t->same('/wp/uploads/editorial-checklist.pdf', $plan['arguments']['filename']);
        $t->same('/wp/marker-output', $plan['arguments']['output']);
        $t->same([
            'max_pages' => 10,
            'start_page' => -20,
            'langs' => 'English,French',
            'batch_multiplier' => 32,
        ], $plan['arguments']['options']);
        $t->same(false, $plan['arguments']['defaults_applied']['max_pages']);
        $t->same(false, $plan['arguments']['defaults_applied']['start_page']);
        $t->same(false, $plan['arguments']['defaults_applied']['batch_multiplier']);
        $t->same(['English', 'French'], $plan['language_parse']['parsed_langs']);
        $t->same(false, $plan['semantic_boundaries']['max_pages_less_than_one_deferred_to_convert_single_pdf']);
        $t->same(true, $plan['semantic_boundaries']['negative_start_page_allowed_by_argparse']);
        $t->same(false, $plan['semantic_boundaries']['batch_multiplier_less_than_one_deferred_to_convert_single_pdf']);
        $t->same('load_all_models', $plan['next_stage']);
        $t->same(false, $plan['parse_args']['filesystem_touched_before_error']);
        $t->same(false, $plan['executes_python_or_models']);
        $t->same(false, $plan['executes_multiprocessing']);
        $t->same(false, $plan['executes_external_pdf_tools']);

        $history = $plan['arguments']['option_value_history'];
        $t->same(4, count($history));
        $t->same(10, $history[0]['value']);
        $t->same(-20, $history[1]['value']);
        $t->same(32, $history[2]['value']);
        $t->same('English,French', $history[3]['value']);

        $t->same(false, $invalidDouble['parse_args']['parse_args_success']);
        $t->same('--max_pages', $invalidDouble['parse_args']['error_argument']);
        $t->same("argument --max_pages: invalid int value: '1__0'", $invalidDouble['parse_args']['error_message']);
        $t->same(false, $invalidDouble['parse_args']['filesystem_touched_before_error']);
        $t->same(false, $invalidTrailing['parse_args']['parse_args_success']);
        $t->same('--batch_multiplier', $invalidTrailing['parse_args']['error_argument']);
        $t->same("argument --batch_multiplier: invalid int value: '10_'", $invalidTrailing['parse_args']['error_message']);
        $t->same(false, $invalidTrailing['executes_python_or_models']);
    },
];
