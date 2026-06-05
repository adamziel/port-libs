<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;
use PortLibs\MarkerPDF\SingleDocumentConverter;

return [
    'records convert.py argparse defaults and failures before runtime side effects' => static function (TestRunner $t): void {
        $batch = new BatchConverter();

        $defaults = $batch->runtimeMainArgumentPreflightPlan(['/wp/uploads', '/wp/marker-output']);

        $t->same('markerpdf.convert_main_argparse_preflight.v1', $defaults['schema']);
        $t->contains('convert.py::main argparse', $defaults['source']);
        $t->same(true, $defaults['parse_args']['parse_args_reached']);
        $t->same(true, $defaults['parse_args']['parse_args_success']);
        $t->same(0, $defaults['parse_args']['exit_code']);
        $t->same('/wp/uploads', $defaults['arguments']['in_folder']);
        $t->same('/wp/marker-output', $defaults['arguments']['out_folder']);
        $t->same([
            'chunk_idx' => 0,
            'num_chunks' => 1,
            'max' => null,
            'workers' => 5,
            'metadata_file' => null,
            'min_length' => null,
        ], $defaults['arguments']['options']);
        $t->same([
            'chunk_idx' => true,
            'num_chunks' => true,
            'max' => true,
            'workers' => true,
            'metadata_file' => true,
            'min_length' => true,
        ], $defaults['arguments']['defaults_applied']);
        $t->same([], $defaults['blocked_stages']);
        $t->same('abspath_input_output', $defaults['next_stage']);
        $t->same(false, $defaults['semantic_boundaries']['num_chunks_less_than_one_deferred_to_chunk_files']);
        $t->same(false, $defaults['semantic_boundaries']['workers_less_than_one_deferred_to_pool_creation']);
        $t->same(false, $defaults['executes_python_or_models']);
        $t->same(false, $defaults['executes_multiprocessing']);
        $t->same(false, $defaults['executes_external_pdf_tools']);

        $custom = $batch->runtimeMainArgumentPreflightPlan([
            '--chunk_idx',
            '-2',
            '--num_chunks=0',
            '--max',
            '-1',
            '--workers',
            '0',
            '--metadata_file',
            'metadata.json',
            '--min_length=-5',
            '/wp/uploads',
            '/wp/marker-output',
        ]);

        $t->same(true, $custom['parse_args']['parse_args_success']);
        $t->same([
            'chunk_idx' => -2,
            'num_chunks' => 0,
            'max' => -1,
            'workers' => 0,
            'metadata_file' => 'metadata.json',
            'min_length' => -5,
        ], $custom['arguments']['options']);
        $t->same([
            'chunk_idx' => false,
            'num_chunks' => false,
            'max' => false,
            'workers' => false,
            'metadata_file' => false,
            'min_length' => false,
        ], $custom['arguments']['defaults_applied']);
        $t->same(true, $custom['semantic_boundaries']['negative_chunk_idx_allowed_by_argparse']);
        $t->same(true, $custom['semantic_boundaries']['num_chunks_less_than_one_deferred_to_chunk_files']);
        $t->same(true, $custom['semantic_boundaries']['negative_max_allowed_by_argparse']);
        $t->same(true, $custom['semantic_boundaries']['workers_less_than_one_deferred_to_pool_creation']);
        $t->same(true, $custom['semantic_boundaries']['negative_min_length_allowed_by_argparse']);
        $t->same(false, $custom['semantic_boundaries']['input_folder_exists_checked_by_argparse']);
        $t->same(false, $custom['semantic_boundaries']['output_folder_exists_checked_by_argparse']);

        $abbreviated = $batch->runtimeMainArgumentPreflightPlan([
            '--work',
            '3',
            '/wp/uploads',
            '/wp/marker-output',
        ]);
        $t->same(true, $abbreviated['parse_args']['parse_args_success']);
        $t->same(3, $abbreviated['arguments']['options']['workers']);
        $t->same(false, $abbreviated['arguments']['defaults_applied']['workers']);

        $ambiguous = $batch->runtimeMainArgumentPreflightPlan([
            '--m',
            '3',
            '/wp/uploads',
            '/wp/marker-output',
        ]);
        $t->same(false, $ambiguous['parse_args']['parse_args_success']);
        $t->same('--m', $ambiguous['parse_args']['error_argument']);
        $t->contains('ambiguous option: --m could match --max, --metadata_file, --min_length', $ambiguous['parse_args']['error_message']);

        $invalidInteger = $batch->runtimeMainArgumentPreflightPlan([
            '/wp/uploads',
            '/wp/marker-output',
            '--workers',
            'many',
        ]);
        $t->same(false, $invalidInteger['parse_args']['parse_args_success']);
        $t->same('argparse-system-exit', $invalidInteger['parse_args']['error_boundary']);
        $t->same('SystemExit', $invalidInteger['parse_args']['error_class']);
        $t->same(2, $invalidInteger['parse_args']['exit_code']);
        $t->same('--workers', $invalidInteger['parse_args']['error_argument']);
        $t->contains("invalid int value: 'many'", $invalidInteger['parse_args']['error_message']);
        $t->same(null, $invalidInteger['arguments']);
        $t->same('parse_args', $invalidInteger['blocked_by']);
        $t->same([
            'abspath_input_output',
            'list_input_files',
            'makedirs_output_exist_ok',
            'chunk_files',
            'load_metadata_file',
            'set_spawn_start_method',
            'prepare_model_handoff',
            'print_conversion_summary',
            'build_task_args',
            'pool_imap_process_single_pdf',
        ], $invalidInteger['blocked_stages']);
        $t->same(false, $invalidInteger['executes_python_or_models']);

        $missingPositional = $batch->runtimeMainArgumentPreflightPlan(['--workers', '2', '/wp/uploads']);
        $t->same(false, $missingPositional['parse_args']['parse_args_success']);
        $t->same('out_folder', $missingPositional['parse_args']['missing_required_arguments'][0]);
        $t->contains('the following arguments are required: out_folder', $missingPositional['parse_args']['error_message']);
        $t->same(false, $missingPositional['parse_args']['filesystem_touched_before_error']);

        $missingOptionValue = $batch->runtimeMainArgumentPreflightPlan([
            '/wp/uploads',
            '/wp/marker-output',
            '--metadata_file',
        ]);
        $t->same(false, $missingOptionValue['parse_args']['parse_args_success']);
        $t->same('--metadata_file', $missingOptionValue['parse_args']['error_argument']);
        $t->contains('expected one argument', $missingOptionValue['parse_args']['error_message']);

        $unknownOption = $batch->runtimeMainArgumentPreflightPlan([
            '/wp/uploads',
            '/wp/marker-output',
            '--gpu',
        ]);
        $t->same(false, $unknownOption['parse_args']['parse_args_success']);
        $t->same('--gpu', $unknownOption['parse_args']['error_argument']);
        $t->contains('unrecognized arguments: --gpu', $unknownOption['parse_args']['error_message']);
        $t->same(false, $unknownOption['executes_multiprocessing']);
    },
    'records convert_single.py argparse defaults and failures before model loading' => static function (TestRunner $t): void {
        $single = new SingleDocumentConverter();

        $defaults = $single->runtimeArgumentPreflightPlan([
            '/wp/uploads/editorial-checklist.pdf',
            '/wp/marker-output',
        ]);

        $t->same('markerpdf.convert_single_argparse_preflight.v1', $defaults['schema']);
        $t->contains('convert_single.py::main argparse', $defaults['source']);
        $t->same(true, $defaults['parse_args']['parse_args_reached']);
        $t->same(true, $defaults['parse_args']['parse_args_success']);
        $t->same(0, $defaults['parse_args']['exit_code']);
        $t->same('/wp/uploads/editorial-checklist.pdf', $defaults['arguments']['filename']);
        $t->same('/wp/marker-output', $defaults['arguments']['output']);
        $t->same([
            'max_pages' => null,
            'start_page' => null,
            'langs' => null,
            'batch_multiplier' => 2,
        ], $defaults['arguments']['options']);
        $t->same([
            'max_pages' => true,
            'start_page' => true,
            'langs' => true,
            'batch_multiplier' => true,
        ], $defaults['arguments']['defaults_applied']);
        $t->same(null, $defaults['language_parse']['parsed_langs']);
        $t->same(false, $defaults['language_parse']['empty_langs_string_becomes_none']);
        $t->same('load_all_models', $defaults['next_stage']);
        $t->same(false, $defaults['semantic_boundaries']['filename_exists_checked_by_argparse']);
        $t->same(false, $defaults['semantic_boundaries']['output_folder_exists_checked_by_argparse']);
        $t->same(false, $defaults['executes_python_or_models']);
        $t->same(false, $defaults['executes_external_pdf_tools']);

        $custom = $single->runtimeArgumentPreflightPlan([
            '--max_pages',
            '0',
            '--start_page',
            '-2',
            '--langs',
            'English, Spanish,de',
            '--batch_multiplier=0',
            '/wp/uploads/editorial-checklist.pdf',
            '/wp/marker-output',
        ]);

        $t->same(true, $custom['parse_args']['parse_args_success']);
        $t->same([
            'max_pages' => 0,
            'start_page' => -2,
            'langs' => 'English, Spanish,de',
            'batch_multiplier' => 0,
        ], $custom['arguments']['options']);
        $t->same([
            'max_pages' => false,
            'start_page' => false,
            'langs' => false,
            'batch_multiplier' => false,
        ], $custom['arguments']['defaults_applied']);
        $t->same(['English', ' Spanish', 'de'], $custom['language_parse']['parsed_langs']);
        $t->same(true, $custom['language_parse']['whitespace_preserved']);
        $t->same(true, $custom['semantic_boundaries']['max_pages_less_than_one_deferred_to_convert_single_pdf']);
        $t->same(true, $custom['semantic_boundaries']['negative_start_page_allowed_by_argparse']);
        $t->same(true, $custom['semantic_boundaries']['batch_multiplier_less_than_one_deferred_to_convert_single_pdf']);

        $emptyLangs = $single->runtimeArgumentPreflightPlan([
            '--langs=',
            '/wp/uploads/editorial-checklist.pdf',
            '/wp/marker-output',
        ]);
        $t->same('', $emptyLangs['arguments']['options']['langs']);
        $t->same(null, $emptyLangs['language_parse']['parsed_langs']);
        $t->same(true, $emptyLangs['language_parse']['empty_langs_string_becomes_none']);

        $abbreviated = $single->runtimeArgumentPreflightPlan([
            '--batch',
            '4',
            '/wp/uploads/editorial-checklist.pdf',
            '/wp/marker-output',
        ]);
        $t->same(4, $abbreviated['arguments']['options']['batch_multiplier']);
        $t->same(false, $abbreviated['arguments']['defaults_applied']['batch_multiplier']);

        $invalidInteger = $single->runtimeArgumentPreflightPlan([
            '/wp/uploads/editorial-checklist.pdf',
            '/wp/marker-output',
            '--max_pages',
            'many',
        ]);
        $t->same(false, $invalidInteger['parse_args']['parse_args_success']);
        $t->same('argparse-system-exit', $invalidInteger['parse_args']['error_boundary']);
        $t->same('SystemExit', $invalidInteger['parse_args']['error_class']);
        $t->same(2, $invalidInteger['parse_args']['exit_code']);
        $t->same('--max_pages', $invalidInteger['parse_args']['error_argument']);
        $t->contains("invalid int value: 'many'", $invalidInteger['parse_args']['error_message']);
        $t->same(null, $invalidInteger['arguments']);
        $t->same('parse_args', $invalidInteger['blocked_by']);
        $t->same([
            'parse_langs',
            'load_all_models',
            'convert_single_pdf',
            'save_markdown',
            'print_saved_folder',
            'print_total_time',
        ], $invalidInteger['blocked_stages']);
        $t->same(false, $invalidInteger['executes_python_or_models']);

        $missingOutput = $single->runtimeArgumentPreflightPlan([
            '--langs',
            'English',
            '/wp/uploads/editorial-checklist.pdf',
        ]);
        $t->same(false, $missingOutput['parse_args']['parse_args_success']);
        $t->same('output', $missingOutput['parse_args']['missing_required_arguments'][0]);
        $t->contains('the following arguments are required: output', $missingOutput['parse_args']['error_message']);
        $t->same(false, $missingOutput['parse_args']['filesystem_touched_before_error']);

        $missingLangValue = $single->runtimeArgumentPreflightPlan([
            '/wp/uploads/editorial-checklist.pdf',
            '/wp/marker-output',
            '--langs',
        ]);
        $t->same(false, $missingLangValue['parse_args']['parse_args_success']);
        $t->same('--langs', $missingLangValue['parse_args']['error_argument']);
        $t->contains('expected one argument', $missingLangValue['parse_args']['error_message']);

        $unknownOption = $single->runtimeArgumentPreflightPlan([
            '/wp/uploads/editorial-checklist.pdf',
            '/wp/marker-output',
            '--workers',
            '3',
        ]);
        $t->same(false, $unknownOption['parse_args']['parse_args_success']);
        $t->same('--workers', $unknownOption['parse_args']['error_argument']);
        $t->contains('unrecognized arguments: --workers', $unknownOption['parse_args']['error_message']);
        $t->same(false, $unknownOption['executes_python_or_models']);
    },
];
