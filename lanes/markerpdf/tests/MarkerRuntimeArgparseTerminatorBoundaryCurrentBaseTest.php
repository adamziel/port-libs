<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;
use PortLibs\MarkerPDF\SingleDocumentConverter;

return [
    'records convert.py end-of-options separator before WordPress option-like folders' => static function (
        TestRunner $t
    ): void {
        $batch = new BatchConverter();

        $blocked = $batch->runtimeMainArgumentPreflightPlan([
            '--wp-source',
            '/wp-content/uploads/marker-output',
        ]);
        $allowed = $batch->runtimeMainArgumentPreflightPlan([
            '--workers',
            '2',
            '--',
            '--wp-source',
            '/wp-content/uploads/marker-output',
        ]);

        $t->same(false, $blocked['parse_args']['parse_args_success']);
        $t->same('--wp-source', $blocked['parse_args']['error_argument']);
        $t->contains('unrecognized arguments: --wp-source', $blocked['parse_args']['error_message']);
        $t->same(false, $blocked['parse_args']['filesystem_touched_before_error']);

        $t->same(true, $allowed['parse_args']['parse_args_success']);
        $t->same(true, $allowed['parse_args']['end_of_options_terminator_seen']);
        $t->same(2, $allowed['parse_args']['end_of_options_terminator_index']);
        $t->same(true, $allowed['parse_args']['option_like_tokens_after_terminator_are_positionals']);
        $t->same('--wp-source', $allowed['arguments']['in_folder']);
        $t->same('/wp-content/uploads/marker-output', $allowed['arguments']['out_folder']);
        $t->same(2, $allowed['arguments']['options']['workers']);
        $t->same(false, $allowed['arguments']['defaults_applied']['workers']);
        $t->same(true, $allowed['parser']['supports_end_of_options_terminator']);
        $t->same('--', $allowed['parser']['end_of_options_terminator']);

        $boundary = $allowed['arguments']['end_of_options_boundary'];
        $t->same('convert.py argparse -- end-of-options boundary', $boundary['source']);
        $t->same(true, $boundary['terminator_seen']);
        $t->same(2, $boundary['terminator_index']);
        $t->same(['--wp-source', '/wp-content/uploads/marker-output'], $boundary['tokens_after_terminator']);
        $t->same(['--wp-source'], $boundary['option_like_tokens_after_terminator']);
        $t->same(1, $boundary['option_like_token_count_after_terminator']);
        $t->same(true, $boundary['option_like_tokens_after_terminator_are_positionals']);
        $t->same(false, $boundary['filesystem_touched_before_terminator_handling']);
        $t->same(false, $boundary['executes_python_or_models']);
        $t->same(false, $allowed['semantic_boundaries']['end_of_options_separator_touches_filesystem']);
        $t->same(true, $allowed['semantic_boundaries']['option_like_positionals_allowed_after_terminator']);
        $t->same('abspath_input_output', $allowed['next_stage']);
        $t->same(false, $allowed['executes_multiprocessing']);
        $t->same(false, $allowed['executes_external_pdf_tools']);
    },
    'records convert_single.py end-of-options separator before WordPress option-like filenames' => static function (
        TestRunner $t
    ): void {
        $single = new SingleDocumentConverter();

        $blocked = $single->runtimeArgumentPreflightPlan([
            '--wp-import.pdf',
            '/wp-content/uploads/marker-output',
        ]);
        $allowed = $single->runtimeArgumentPreflightPlan([
            '--max_pages',
            '2',
            '--',
            '--wp-import.pdf',
            '/wp-content/uploads/marker-output',
        ]);

        $t->same(false, $blocked['parse_args']['parse_args_success']);
        $t->same('--wp-import.pdf', $blocked['parse_args']['error_argument']);
        $t->contains('unrecognized arguments: --wp-import.pdf', $blocked['parse_args']['error_message']);
        $t->same(false, $blocked['parse_args']['filesystem_touched_before_error']);

        $t->same(true, $allowed['parse_args']['parse_args_success']);
        $t->same(true, $allowed['parse_args']['end_of_options_terminator_seen']);
        $t->same(2, $allowed['parse_args']['end_of_options_terminator_index']);
        $t->same(true, $allowed['parse_args']['option_like_tokens_after_terminator_are_positionals']);
        $t->same('--wp-import.pdf', $allowed['arguments']['filename']);
        $t->same('/wp-content/uploads/marker-output', $allowed['arguments']['output']);
        $t->same(2, $allowed['arguments']['options']['max_pages']);
        $t->same(false, $allowed['arguments']['defaults_applied']['max_pages']);
        $t->same(true, $allowed['parser']['supports_end_of_options_terminator']);
        $t->same('--', $allowed['parser']['end_of_options_terminator']);

        $boundary = $allowed['arguments']['end_of_options_boundary'];
        $t->same('convert_single.py argparse -- end-of-options boundary', $boundary['source']);
        $t->same(true, $boundary['terminator_seen']);
        $t->same(2, $boundary['terminator_index']);
        $t->same(['--wp-import.pdf', '/wp-content/uploads/marker-output'], $boundary['tokens_after_terminator']);
        $t->same(['--wp-import.pdf'], $boundary['option_like_tokens_after_terminator']);
        $t->same(true, $boundary['option_like_tokens_after_terminator_are_positionals']);
        $t->same(false, $boundary['filesystem_touched_before_terminator_handling']);
        $t->same(false, $boundary['executes_python_or_models']);
        $t->same(false, $allowed['semantic_boundaries']['end_of_options_separator_touches_filesystem']);
        $t->same(true, $allowed['semantic_boundaries']['option_like_positionals_allowed_after_terminator']);
        $t->same('load_all_models', $allowed['next_stage']);
        $t->same(false, $allowed['executes_multiprocessing']);
        $t->same(false, $allowed['executes_external_pdf_tools']);
    },
];
