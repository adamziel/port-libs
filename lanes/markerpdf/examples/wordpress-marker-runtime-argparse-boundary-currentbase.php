<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;
use PortLibs\MarkerPDF\SingleDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$batch = new BatchConverter();
$single = new SingleDocumentConverter();
$defaultPlan = $batch->runtimeMainArgumentPreflightPlan([
    '/wp/uploads',
    '/wp/marker-output',
]);
$customPlan = $batch->runtimeMainArgumentPreflightPlan([
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
$invalidWorkerPlan = $batch->runtimeMainArgumentPreflightPlan([
    '/wp/uploads',
    '/wp/marker-output',
    '--workers',
    'many',
]);
$missingOutputPlan = $batch->runtimeMainArgumentPreflightPlan([
    '--workers',
    '2',
    '/wp/uploads',
]);
$abbrevPlan = $batch->runtimeMainArgumentPreflightPlan([
    '--work',
    '3',
    '/wp/uploads',
    '/wp/marker-output',
]);
$ambiguousPlan = $batch->runtimeMainArgumentPreflightPlan([
    '--m',
    '3',
    '/wp/uploads',
    '/wp/marker-output',
]);
$singleDefaultPlan = $single->runtimeArgumentPreflightPlan([
    '/wp/uploads/editorial-checklist.pdf',
    '/wp/marker-output',
]);
$singleCustomPlan = $single->runtimeArgumentPreflightPlan([
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
$singleEmptyLangsPlan = $single->runtimeArgumentPreflightPlan([
    '--langs=',
    '/wp/uploads/editorial-checklist.pdf',
    '/wp/marker-output',
]);
$singleInvalidMaxPagesPlan = $single->runtimeArgumentPreflightPlan([
    '/wp/uploads/editorial-checklist.pdf',
    '/wp/marker-output',
    '--max_pages',
    'many',
]);
$singleMissingOutputPlan = $single->runtimeArgumentPreflightPlan([
    '--langs',
    'English',
    '/wp/uploads/editorial-checklist.pdf',
]);

if ($defaultPlan['parse_args']['parse_args_success'] !== true) {
    throw new RuntimeException('Expected default runtime argparse plan to parse.');
}
if ($customPlan['semantic_boundaries']['num_chunks_less_than_one_deferred_to_chunk_files'] !== true) {
    throw new RuntimeException('Expected --num_chunks=0 to parse and defer failure to chunking.');
}
if ($invalidWorkerPlan['parse_args']['error_boundary'] !== 'argparse-system-exit') {
    throw new RuntimeException('Expected invalid --workers value to fail at argparse.');
}
if (($missingOutputPlan['parse_args']['missing_required_arguments'][0] ?? null) !== 'out_folder') {
    throw new RuntimeException('Expected missing out_folder to fail at argparse before filesystem checks.');
}
if (($abbrevPlan['arguments']['options']['workers'] ?? null) !== 3) {
    throw new RuntimeException('Expected upstream argparse abbreviation --work to resolve to --workers.');
}
if (!str_contains((string) $ambiguousPlan['parse_args']['error_message'], 'ambiguous option: --m')) {
    throw new RuntimeException('Expected ambiguous --m abbreviation to fail at argparse.');
}
if ($singleDefaultPlan['parse_args']['parse_args_success'] !== true || $singleDefaultPlan['next_stage'] !== 'load_all_models') {
    throw new RuntimeException('Expected convert_single.py default argparse plan to stop before model loading.');
}
if ($singleCustomPlan['language_parse']['parsed_langs'] !== ['English', ' Spanish', 'de']) {
    throw new RuntimeException('Expected convert_single.py --langs parsing to preserve upstream comma-split whitespace.');
}
if ($singleCustomPlan['semantic_boundaries']['max_pages_less_than_one_deferred_to_convert_single_pdf'] !== true) {
    throw new RuntimeException('Expected convert_single.py --max_pages=0 to parse and defer semantic validation.');
}
if ($singleEmptyLangsPlan['language_parse']['empty_langs_string_becomes_none'] !== true) {
    throw new RuntimeException('Expected empty convert_single.py --langs value to become None/null before model loading.');
}
if ($singleInvalidMaxPagesPlan['parse_args']['error_boundary'] !== 'argparse-system-exit') {
    throw new RuntimeException('Expected invalid convert_single.py --max_pages value to fail at argparse.');
}
if (($singleMissingOutputPlan['parse_args']['missing_required_arguments'][0] ?? null) !== 'output') {
    throw new RuntimeException('Expected convert_single.py missing output argument to fail before filesystem checks.');
}

echo json_encode([
    'scenario' => 'wordpress-marker-runtime-argparse-boundary-currentbase',
    'purpose' => 'Review convert.py and convert_single.py CLI argument admission before WordPress PDF queues touch uploads, metadata files, model handoff, multiprocessing, or external PDF tools.',
    'source' => $defaultPlan['source'],
    'default_parse_success' => $defaultPlan['parse_args']['parse_args_success'],
    'default_options' => $defaultPlan['arguments']['options'],
    'custom_options' => $customPlan['arguments']['options'],
    'negative_chunk_idx_allowed_by_argparse' => $customPlan['semantic_boundaries']['negative_chunk_idx_allowed_by_argparse'],
    'zero_num_chunks_deferred_to_chunk_files' => $customPlan['semantic_boundaries']['num_chunks_less_than_one_deferred_to_chunk_files'],
    'negative_max_allowed_by_argparse' => $customPlan['semantic_boundaries']['negative_max_allowed_by_argparse'],
    'zero_workers_deferred_to_pool_creation' => $customPlan['semantic_boundaries']['workers_less_than_one_deferred_to_pool_creation'],
    'negative_min_length_allowed_by_argparse' => $customPlan['semantic_boundaries']['negative_min_length_allowed_by_argparse'],
    'invalid_worker_parse_success' => $invalidWorkerPlan['parse_args']['parse_args_success'],
    'invalid_worker_error_boundary' => $invalidWorkerPlan['parse_args']['error_boundary'],
    'invalid_worker_error_class' => $invalidWorkerPlan['parse_args']['error_class'],
    'invalid_worker_exit_code' => $invalidWorkerPlan['parse_args']['exit_code'],
    'invalid_worker_error_message' => $invalidWorkerPlan['parse_args']['error_message'],
    'missing_out_folder_error' => $missingOutputPlan['parse_args']['error_message'],
    'missing_out_folder_blocks_filesystem' => $missingOutputPlan['parse_args']['filesystem_touched_before_error'] === false,
    'abbreviated_workers_value' => $abbrevPlan['arguments']['options']['workers'],
    'ambiguous_option_error' => $ambiguousPlan['parse_args']['error_message'],
    'invalid_worker_blocked_stages' => $invalidWorkerPlan['blocked_stages'],
    'single_source' => $singleDefaultPlan['source'],
    'single_default_parse_success' => $singleDefaultPlan['parse_args']['parse_args_success'],
    'single_default_options' => $singleDefaultPlan['arguments']['options'],
    'single_custom_options' => $singleCustomPlan['arguments']['options'],
    'single_custom_parsed_langs' => $singleCustomPlan['language_parse']['parsed_langs'],
    'single_whitespace_preserved' => $singleCustomPlan['language_parse']['whitespace_preserved'],
    'single_zero_max_pages_deferred' => $singleCustomPlan['semantic_boundaries']['max_pages_less_than_one_deferred_to_convert_single_pdf'],
    'single_negative_start_page_allowed' => $singleCustomPlan['semantic_boundaries']['negative_start_page_allowed_by_argparse'],
    'single_zero_batch_multiplier_deferred' => $singleCustomPlan['semantic_boundaries']['batch_multiplier_less_than_one_deferred_to_convert_single_pdf'],
    'single_empty_langs_becomes_none' => $singleEmptyLangsPlan['language_parse']['empty_langs_string_becomes_none'],
    'single_invalid_max_pages_error_boundary' => $singleInvalidMaxPagesPlan['parse_args']['error_boundary'],
    'single_invalid_max_pages_exit_code' => $singleInvalidMaxPagesPlan['parse_args']['exit_code'],
    'single_missing_output_error' => $singleMissingOutputPlan['parse_args']['error_message'],
    'single_invalid_parse_blocked_stages' => $singleInvalidMaxPagesPlan['blocked_stages'],
    'executes_python_or_models' => $defaultPlan['executes_python_or_models'],
    'single_executes_python_or_models' => $singleDefaultPlan['executes_python_or_models'],
    'executes_multiprocessing' => $defaultPlan['executes_multiprocessing'] || $singleDefaultPlan['executes_multiprocessing'],
    'executes_external_pdf_tools' => $defaultPlan['executes_external_pdf_tools'] || $singleDefaultPlan['executes_external_pdf_tools'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
