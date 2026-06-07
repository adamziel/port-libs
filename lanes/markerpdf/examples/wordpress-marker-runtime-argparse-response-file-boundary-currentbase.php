<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;
use PortLibs\MarkerPDF\SingleDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$batchPlan = (new BatchConverter())->runtimeMainArgumentPreflightPlan([
    '@wp-batch-args.txt',
    '/wp/marker-output',
    '--metadata_file',
    '@wp-metadata.json',
    '--workers',
    '2',
]);
$batchMissing = (new BatchConverter())->runtimeMainArgumentPreflightPlan([
    '@wp-batch-args.txt',
]);
$singlePlan = (new SingleDocumentConverter())->runtimeArgumentPreflightPlan([
    '--langs',
    '@wp-langs.txt',
    '@wp-single-upload.pdf',
    '/wp/marker-output',
]);

$batchArgfile = $batchPlan['arguments']['argfile_boundary'];
$singleArgfile = $singlePlan['arguments']['argfile_boundary'];

if ($batchPlan['parser']['fromfile_prefix_chars'] !== null || $batchPlan['parser']['expands_response_files'] !== false) {
    throw new RuntimeException('Expected batch argparse response-file expansion to be disabled.');
}
if ($batchPlan['arguments']['in_folder'] !== '@wp-batch-args.txt' || $batchPlan['arguments']['options']['metadata_file'] !== '@wp-metadata.json') {
    throw new RuntimeException('Expected batch @-prefixed values to remain literal argparse tokens.');
}
if ($batchArgfile['reads_at_files_before_parse_args'] !== false || $batchArgfile['tokens_remain_in_argv'] !== true) {
    throw new RuntimeException('Expected batch @-prefixed tokens to stay review-only and unread.');
}
if (($batchMissing['parse_args']['missing_required_arguments'][0] ?? null) !== 'out_folder') {
    throw new RuntimeException('Expected single @-prefixed batch token to be treated as a literal in_folder positional.');
}
if ($singlePlan['arguments']['filename'] !== '@wp-single-upload.pdf' || $singlePlan['arguments']['options']['langs'] !== '@wp-langs.txt') {
    throw new RuntimeException('Expected convert_single.py @-prefixed filename and langs values to remain literal.');
}
if ($singleArgfile['reads_at_files_before_parse_args'] !== false || $singleArgfile['tokens_remain_in_argv'] !== true) {
    throw new RuntimeException('Expected convert_single.py @-prefixed tokens to stay unread before model loading.');
}
if ($batchPlan['executes_python_or_models'] !== false || $singlePlan['executes_python_or_models'] !== false) {
    throw new RuntimeException('Runtime argparse response-file smoke must not execute Python or models.');
}

echo json_encode([
    'scenario' => 'wordpress-marker-runtime-argparse-response-file-boundary-currentbase',
    'purpose' => 'Record that markerPDF convert.py and convert_single.py do not enable argparse response-file expansion, so WordPress @-prefixed upload paths, metadata paths, and language values remain literal tokens before filesystem conversion, metadata loading, model handoff, multiprocessing, or external PDF tools.',
    'batch_parser_fromfile_prefix_chars' => $batchPlan['parser']['fromfile_prefix_chars'],
    'batch_expands_response_files' => $batchPlan['parser']['expands_response_files'],
    'batch_in_folder_literal' => $batchPlan['arguments']['in_folder'],
    'batch_metadata_file_literal' => $batchPlan['arguments']['options']['metadata_file'],
    'batch_at_prefixed_tokens' => $batchArgfile['at_prefixed_tokens'],
    'batch_reads_at_files_before_parse_args' => $batchArgfile['reads_at_files_before_parse_args'],
    'batch_missing_out_folder_error' => $batchMissing['parse_args']['error_message'],
    'single_parser_fromfile_prefix_chars' => $singlePlan['parser']['fromfile_prefix_chars'],
    'single_expands_response_files' => $singlePlan['parser']['expands_response_files'],
    'single_filename_literal' => $singlePlan['arguments']['filename'],
    'single_langs_literal' => $singlePlan['arguments']['options']['langs'],
    'single_parsed_langs' => $singlePlan['language_parse']['parsed_langs'],
    'single_at_prefixed_tokens' => $singleArgfile['at_prefixed_tokens'],
    'single_reads_at_files_before_parse_args' => $singleArgfile['reads_at_files_before_parse_args'],
    'executes_python_or_models' => $batchPlan['executes_python_or_models'] || $singlePlan['executes_python_or_models'],
    'executes_multiprocessing' => $batchPlan['executes_multiprocessing'] || $singlePlan['executes_multiprocessing'],
    'executes_external_pdf_tools' => $batchPlan['executes_external_pdf_tools'] || $singlePlan['executes_external_pdf_tools'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
