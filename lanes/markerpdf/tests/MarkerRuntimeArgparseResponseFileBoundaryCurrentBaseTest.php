<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;
use PortLibs\MarkerPDF\SingleDocumentConverter;

return [
    'keeps argparse at-file tokens literal before runtime preflight' => static function (TestRunner $t): void {
        $batch = new BatchConverter();
        $batchLiteral = $batch->runtimeMainArgumentPreflightPlan([
            '@wp-batch-args.txt',
            '/wp/marker-output',
            '--metadata_file',
            '@wp-metadata.json',
            '--workers',
            '2',
        ]);
        $batchMissing = $batch->runtimeMainArgumentPreflightPlan([
            '@wp-batch-args.txt',
        ]);

        $t->same(null, $batchLiteral['parser']['fromfile_prefix_chars']);
        $t->same(false, $batchLiteral['parser']['expands_response_files']);
        $t->same(true, $batchLiteral['parser']['at_file_tokens_are_literals']);
        $t->same(true, $batchLiteral['parse_args']['parse_args_success']);
        $t->same('@wp-batch-args.txt', $batchLiteral['arguments']['in_folder']);
        $t->same('/wp/marker-output', $batchLiteral['arguments']['out_folder']);
        $t->same('@wp-metadata.json', $batchLiteral['arguments']['options']['metadata_file']);
        $t->same(true, $batchLiteral['semantic_boundaries']['metadata_file_truthy_for_json_load']);
        $t->same(false, $batchLiteral['semantic_boundaries']['fromfile_prefix_chars_configured']);
        $t->same(false, $batchLiteral['semantic_boundaries']['at_file_tokens_expand_before_parse']);
        $t->same(true, $batchLiteral['semantic_boundaries']['at_prefixed_tokens_seen']);
        $t->same(true, $batchLiteral['semantic_boundaries']['at_prefixed_tokens_are_literal_cli_values']);

        $batchArgfile = $batchLiteral['arguments']['argfile_boundary'];
        $t->same('convert.py argparse.ArgumentParser response-file boundary', $batchArgfile['source']);
        $t->same('argparse.ArgumentParser(description="Convert multiple pdfs to markdown.")', $batchArgfile['argument_parser_call']);
        $t->same(null, $batchArgfile['fromfile_prefix_chars']);
        $t->same(false, $batchArgfile['response_file_expansion_enabled']);
        $t->same(['@wp-batch-args.txt', '@wp-metadata.json'], $batchArgfile['at_prefixed_tokens']);
        $t->same(2, $batchArgfile['at_prefixed_token_count']);
        $t->same(true, $batchArgfile['tokens_remain_in_argv']);
        $t->same(false, $batchArgfile['reads_at_files_before_parse_args']);
        $t->same(false, $batchArgfile['filesystem_touched_before_error']);
        $t->same(false, $batchArgfile['executes_python_or_models']);
        $t->same(false, $batchArgfile['executes_multiprocessing']);
        $t->same(false, $batchArgfile['executes_external_pdf_tools']);

        $t->same(false, $batchMissing['parse_args']['parse_args_success']);
        $t->same(['out_folder'], $batchMissing['parse_args']['missing_required_arguments']);
        $t->contains('the following arguments are required: out_folder', $batchMissing['parse_args']['error_message']);
        $t->same(true, $batchMissing['semantic_boundaries']['at_prefixed_tokens_seen']);
        $t->same(false, $batchMissing['semantic_boundaries']['at_file_tokens_expand_before_parse']);
        $t->same(['@wp-batch-args.txt'], $batchMissing['semantic_boundaries']['argfile_boundary']['at_prefixed_tokens']);
        $t->same(false, $batchMissing['semantic_boundaries']['argfile_boundary']['reads_at_files_before_parse_args']);

        $single = new SingleDocumentConverter();
        $singleLiteral = $single->runtimeArgumentPreflightPlan([
            '--langs',
            '@wp-langs.txt',
            '@wp-single-upload.pdf',
            '/wp/marker-output',
        ]);
        $singleMissing = $single->runtimeArgumentPreflightPlan([
            '@wp-single-args.txt',
        ]);

        $t->same(null, $singleLiteral['parser']['fromfile_prefix_chars']);
        $t->same(false, $singleLiteral['parser']['expands_response_files']);
        $t->same(true, $singleLiteral['parser']['at_file_tokens_are_literals']);
        $t->same(true, $singleLiteral['parse_args']['parse_args_success']);
        $t->same('@wp-single-upload.pdf', $singleLiteral['arguments']['filename']);
        $t->same('/wp/marker-output', $singleLiteral['arguments']['output']);
        $t->same('@wp-langs.txt', $singleLiteral['arguments']['options']['langs']);
        $t->same(['@wp-langs.txt'], $singleLiteral['language_parse']['parsed_langs']);
        $t->same(false, $singleLiteral['semantic_boundaries']['fromfile_prefix_chars_configured']);
        $t->same(false, $singleLiteral['semantic_boundaries']['at_file_tokens_expand_before_parse']);
        $t->same(true, $singleLiteral['semantic_boundaries']['at_prefixed_tokens_seen']);
        $t->same(true, $singleLiteral['semantic_boundaries']['at_prefixed_tokens_are_literal_cli_values']);

        $singleArgfile = $singleLiteral['arguments']['argfile_boundary'];
        $t->same('convert_single.py argparse.ArgumentParser response-file boundary', $singleArgfile['source']);
        $t->same('argparse.ArgumentParser()', $singleArgfile['argument_parser_call']);
        $t->same(null, $singleArgfile['fromfile_prefix_chars']);
        $t->same(false, $singleArgfile['response_file_expansion_enabled']);
        $t->same(['@wp-langs.txt', '@wp-single-upload.pdf'], $singleArgfile['at_prefixed_tokens']);
        $t->same(true, $singleArgfile['tokens_remain_in_argv']);
        $t->same(false, $singleArgfile['reads_at_files_before_parse_args']);
        $t->same(false, $singleArgfile['filesystem_touched_before_error']);
        $t->same(false, $singleArgfile['executes_python_or_models']);
        $t->same(false, $singleArgfile['executes_external_pdf_tools']);

        $t->same(false, $singleMissing['parse_args']['parse_args_success']);
        $t->same(['output'], $singleMissing['parse_args']['missing_required_arguments']);
        $t->contains('the following arguments are required: output', $singleMissing['parse_args']['error_message']);
        $t->same(true, $singleMissing['semantic_boundaries']['at_prefixed_tokens_seen']);
        $t->same(false, $singleMissing['semantic_boundaries']['argfile_boundary']['reads_at_files_before_parse_args']);
    },
];
