<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;
use PortLibs\MarkerPDF\SingleDocumentConverter;

return [
    'records argparse repeated batch options with last occurrence winning before runtime side effects' => static function (
        TestRunner $t
    ): void {
        $plan = (new BatchConverter())->runtimeMainArgumentPreflightPlan([
            '--metadata_file',
            'stale-metadata.json',
            '--workers',
            '2',
            '--min_length',
            '250',
            '--metadata_file=current-metadata.json',
            '--workers=4',
            '--min_length=0',
            '/wp/uploads/marker-batch',
            '/wp/marker-output',
        ]);

        $t->same(true, $plan['parse_args']['parse_args_success']);
        $t->same('/wp/uploads/marker-batch', $plan['arguments']['in_folder']);
        $t->same('/wp/marker-output', $plan['arguments']['out_folder']);
        $t->same('current-metadata.json', $plan['arguments']['options']['metadata_file']);
        $t->same(4, $plan['arguments']['options']['workers']);
        $t->same(0, $plan['arguments']['options']['min_length']);
        $t->same(false, $plan['arguments']['defaults_applied']['metadata_file']);
        $t->same(false, $plan['arguments']['defaults_applied']['workers']);
        $t->same(false, $plan['arguments']['defaults_applied']['min_length']);

        $t->same([
            'chunk_idx' => 0,
            'num_chunks' => 0,
            'max' => 0,
            'workers' => 2,
            'metadata_file' => 2,
            'min_length' => 2,
        ], $plan['arguments']['option_occurrences']);
        $t->same(['workers', 'metadata_file', 'min_length'], $plan['arguments']['repeated_options']);
        $t->same([
            'workers' => 2,
            'metadata_file' => 2,
            'min_length' => 2,
        ], $plan['arguments']['repeated_option_counts']);
        $t->same(true, $plan['arguments']['last_occurrence_wins']);
        $t->same(true, $plan['semantic_boundaries']['repeated_options_last_occurrence_wins']);

        $history = $plan['arguments']['option_value_history'];
        $t->same(6, count($history));
        $t->same([
            'option' => '--metadata_file',
            'key' => 'metadata_file',
            'value' => 'stale-metadata.json',
            'value_type' => 'str',
            'occurrence' => 1,
            'previous_value' => null,
            'overrides_previous' => false,
        ], $history[0]);
        $t->same([
            'option' => '--metadata_file',
            'key' => 'metadata_file',
            'value' => 'current-metadata.json',
            'value_type' => 'str',
            'occurrence' => 2,
            'previous_value' => 'stale-metadata.json',
            'overrides_previous' => true,
        ], $history[3]);
        $t->same([
            'option' => '--workers',
            'key' => 'workers',
            'value' => 4,
            'value_type' => 'int',
            'occurrence' => 2,
            'previous_value' => 2,
            'overrides_previous' => true,
        ], $history[4]);
        $t->same([
            'option' => '--min_length',
            'key' => 'min_length',
            'value' => 0,
            'value_type' => 'int',
            'occurrence' => 2,
            'previous_value' => 250,
            'overrides_previous' => true,
        ], $history[5]);

        $t->same(true, $plan['semantic_boundaries']['metadata_file_truthy_for_json_load']);
        $t->same(false, $plan['semantic_boundaries']['empty_metadata_file_skips_json_load']);
        $t->same(false, $plan['semantic_boundaries']['workers_less_than_one_deferred_to_pool_creation']);
        $t->same(false, $plan['semantic_boundaries']['negative_min_length_allowed_by_argparse']);
        $t->same(false, $plan['parse_args']['filesystem_touched_before_error']);
        $t->same('abspath_input_output', $plan['next_stage']);
        $t->same(false, $plan['executes_python_or_models']);
        $t->same(false, $plan['executes_multiprocessing']);
        $t->same(false, $plan['executes_external_pdf_tools']);
    },
    'records convert single repeated options with last occurrence winning before model loading' => static function (
        TestRunner $t
    ): void {
        $plan = (new SingleDocumentConverter())->runtimeArgumentPreflightPlan([
            '--langs',
            'English',
            '--max_pages',
            '1',
            '--start_page',
            '0',
            '--batch_multiplier',
            '2',
            '--langs=Spanish,French',
            '--max_pages=5',
            '--start_page=2',
            '--batch_multiplier=4',
            '/wp/uploads/editorial.pdf',
            '/wp/marker-output',
        ]);

        $t->same(true, $plan['parse_args']['parse_args_success']);
        $t->same('/wp/uploads/editorial.pdf', $plan['arguments']['filename']);
        $t->same('/wp/marker-output', $plan['arguments']['output']);
        $t->same('Spanish,French', $plan['arguments']['options']['langs']);
        $t->same(5, $plan['arguments']['options']['max_pages']);
        $t->same(2, $plan['arguments']['options']['start_page']);
        $t->same(4, $plan['arguments']['options']['batch_multiplier']);
        $t->same(['Spanish', 'French'], $plan['language_parse']['parsed_langs']);
        $t->same(false, $plan['arguments']['defaults_applied']['langs']);
        $t->same(false, $plan['arguments']['defaults_applied']['max_pages']);
        $t->same(false, $plan['arguments']['defaults_applied']['start_page']);
        $t->same(false, $plan['arguments']['defaults_applied']['batch_multiplier']);

        $t->same([
            'max_pages' => 2,
            'start_page' => 2,
            'langs' => 2,
            'batch_multiplier' => 2,
        ], $plan['arguments']['option_occurrences']);
        $t->same(['max_pages', 'start_page', 'langs', 'batch_multiplier'], $plan['arguments']['repeated_options']);
        $t->same([
            'max_pages' => 2,
            'start_page' => 2,
            'langs' => 2,
            'batch_multiplier' => 2,
        ], $plan['arguments']['repeated_option_counts']);
        $t->same(true, $plan['arguments']['last_occurrence_wins']);
        $t->same(true, $plan['semantic_boundaries']['repeated_options_last_occurrence_wins']);

        $history = $plan['arguments']['option_value_history'];
        $t->same(8, count($history));
        $t->same([
            'option' => '--langs',
            'key' => 'langs',
            'value' => 'English',
            'value_type' => 'str',
            'occurrence' => 1,
            'previous_value' => null,
            'overrides_previous' => false,
        ], $history[0]);
        $t->same([
            'option' => '--batch_multiplier',
            'key' => 'batch_multiplier',
            'value' => 2,
            'value_type' => 'int',
            'occurrence' => 1,
            'previous_value' => null,
            'overrides_previous' => false,
        ], $history[3]);
        $t->same([
            'option' => '--langs',
            'key' => 'langs',
            'value' => 'Spanish,French',
            'value_type' => 'str',
            'occurrence' => 2,
            'previous_value' => 'English',
            'overrides_previous' => true,
        ], $history[4]);
        $t->same([
            'option' => '--batch_multiplier',
            'key' => 'batch_multiplier',
            'value' => 4,
            'value_type' => 'int',
            'occurrence' => 2,
            'previous_value' => 2,
            'overrides_previous' => true,
        ], $history[7]);

        $t->same(false, $plan['parse_args']['filesystem_touched_before_error']);
        $t->same('load_all_models', $plan['next_stage']);
        $t->same(false, $plan['executes_python_or_models']);
        $t->same(false, $plan['executes_multiprocessing']);
        $t->same(false, $plan['executes_external_pdf_tools']);
    },
];
