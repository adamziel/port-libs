<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;
use PortLibs\MarkerPDF\SingleDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$batch = new BatchConverter();
$single = new SingleDocumentConverter();

$batchPlan = $batch->runtimeMainArgumentPreflightPlan([
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
$batchInvalidPlan = $batch->runtimeMainArgumentPreflightPlan([
    '/wp/uploads/marker-batch',
    '/wp/marker-output',
    '--workers',
    '1__0',
]);

$singlePlan = $single->runtimeArgumentPreflightPlan([
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
$singleInvalidPlan = $single->runtimeArgumentPreflightPlan([
    '/wp/uploads/editorial-checklist.pdf',
    '/wp/marker-output',
    '--batch_multiplier=10_',
]);

if (($batchPlan['arguments']['options']['workers'] ?? null) !== 10) {
    throw new RuntimeException('Expected convert.py --workers 1_0 to parse like Python int().');
}
if (($batchPlan['arguments']['options']['chunk_idx'] ?? null) !== 12) {
    throw new RuntimeException('Expected convert.py --chunk_idx +1_2 to parse like Python int().');
}
if (($batchPlan['arguments']['options']['max'] ?? null) !== -30) {
    throw new RuntimeException('Expected convert.py --max -3_0 to parse like Python int().');
}
if (($batchPlan['arguments']['options']['min_length'] ?? null) !== 80) {
    throw new RuntimeException('Expected convert.py --min_length 8_0 to parse like Python int().');
}
if ($batchInvalidPlan['parse_args']['parse_args_success'] !== false) {
    throw new RuntimeException('Expected malformed convert.py underscore integer to fail at argparse.');
}
if (($singlePlan['arguments']['options']['max_pages'] ?? null) !== 10) {
    throw new RuntimeException('Expected convert_single.py --max_pages 1_0 to parse like Python int().');
}
if (($singlePlan['arguments']['options']['start_page'] ?? null) !== -20) {
    throw new RuntimeException('Expected convert_single.py --start_page -2_0 to parse like Python int().');
}
if (($singlePlan['arguments']['options']['batch_multiplier'] ?? null) !== 32) {
    throw new RuntimeException('Expected convert_single.py --batch_multiplier +3_2 to parse like Python int().');
}
if ($singleInvalidPlan['parse_args']['parse_args_success'] !== false) {
    throw new RuntimeException('Expected malformed convert_single.py underscore integer to fail at argparse.');
}

echo json_encode([
    'scenario' => 'wordpress-marker-runtime-argparse-underscore-integer-currentbase',
    'purpose' => 'Review Python argparse int() underscore separator parity before WordPress PDF queues touch uploads, metadata files, model handoff, multiprocessing, or external PDF tools.',
    'source' => 'sddai/markerPDF convert.py and convert_single.py argparse type=int boundaries',
    'batch_parse_success' => $batchPlan['parse_args']['parse_args_success'],
    'batch_chunk_idx' => $batchPlan['arguments']['options']['chunk_idx'],
    'batch_num_chunks' => $batchPlan['arguments']['options']['num_chunks'],
    'batch_max' => $batchPlan['arguments']['options']['max'],
    'batch_workers' => $batchPlan['arguments']['options']['workers'],
    'batch_min_length' => $batchPlan['arguments']['options']['min_length'],
    'batch_negative_max_allowed_by_argparse' => $batchPlan['semantic_boundaries']['negative_max_allowed_by_argparse'],
    'batch_invalid_parse_success' => $batchInvalidPlan['parse_args']['parse_args_success'],
    'batch_invalid_error_message' => $batchInvalidPlan['parse_args']['error_message'],
    'single_parse_success' => $singlePlan['parse_args']['parse_args_success'],
    'single_max_pages' => $singlePlan['arguments']['options']['max_pages'],
    'single_start_page' => $singlePlan['arguments']['options']['start_page'],
    'single_batch_multiplier' => $singlePlan['arguments']['options']['batch_multiplier'],
    'single_parsed_langs' => $singlePlan['language_parse']['parsed_langs'],
    'single_negative_start_page_allowed_by_argparse' => $singlePlan['semantic_boundaries']['negative_start_page_allowed_by_argparse'],
    'single_invalid_parse_success' => $singleInvalidPlan['parse_args']['parse_args_success'],
    'single_invalid_error_message' => $singleInvalidPlan['parse_args']['error_message'],
    'filesystem_touched_before_error' => $batchInvalidPlan['parse_args']['filesystem_touched_before_error']
        || $singleInvalidPlan['parse_args']['filesystem_touched_before_error'],
    'executes_python_or_models' => $batchPlan['executes_python_or_models'] || $singlePlan['executes_python_or_models'],
    'executes_multiprocessing' => $batchPlan['executes_multiprocessing'] || $singlePlan['executes_multiprocessing'],
    'executes_external_pdf_tools' => $batchPlan['executes_external_pdf_tools'] || $singlePlan['executes_external_pdf_tools'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
