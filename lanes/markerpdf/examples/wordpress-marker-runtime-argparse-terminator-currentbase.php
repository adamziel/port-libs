<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;
use PortLibs\MarkerPDF\SingleDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$batch = new BatchConverter();
$single = new SingleDocumentConverter();

$blockedBatch = $batch->runtimeMainArgumentPreflightPlan([
    '--wp-source',
    '/var/www/html/wp-content/uploads/marker-output',
]);
$allowedBatch = $batch->runtimeMainArgumentPreflightPlan([
    '--workers',
    '2',
    '--',
    '--wp-source',
    '/var/www/html/wp-content/uploads/marker-output',
]);
$blockedSingle = $single->runtimeArgumentPreflightPlan([
    '--wp-import.pdf',
    '/var/www/html/wp-content/uploads/marker-output',
]);
$allowedSingle = $single->runtimeArgumentPreflightPlan([
    '--max_pages',
    '2',
    '--',
    '--wp-import.pdf',
    '/var/www/html/wp-content/uploads/marker-output',
]);

if ($blockedBatch['parse_args']['parse_args_success'] !== false) {
    throw new RuntimeException('Expected option-looking batch input path to be rejected before --.');
}
if ($allowedBatch['parse_args']['parse_args_success'] !== true || $allowedBatch['arguments']['in_folder'] !== '--wp-source') {
    throw new RuntimeException('Expected -- to admit option-looking batch input folder as a positional.');
}
if ($blockedSingle['parse_args']['parse_args_success'] !== false) {
    throw new RuntimeException('Expected option-looking single filename to be rejected before --.');
}
if ($allowedSingle['parse_args']['parse_args_success'] !== true || $allowedSingle['arguments']['filename'] !== '--wp-import.pdf') {
    throw new RuntimeException('Expected -- to admit option-looking single filename as a positional.');
}
if ($allowedBatch['executes_python_or_models'] !== false || $allowedSingle['executes_python_or_models'] !== false) {
    throw new RuntimeException('Argparse terminator smoke must not execute Python or model code.');
}
if ($allowedBatch['executes_external_pdf_tools'] !== false || $allowedSingle['executes_external_pdf_tools'] !== false) {
    throw new RuntimeException('Argparse terminator smoke must not execute external PDF tools.');
}

echo json_encode([
    'scenario' => 'wordpress-marker-runtime-argparse-terminator-currentbase',
    'purpose' => 'Review markerPDF argparse -- terminator handling for WordPress imports whose folder or uploaded filename begins with option-looking dashes before any filesystem, Python, model, or PDF tool work.',
    'batch_source' => $allowedBatch['source'],
    'single_source' => $allowedSingle['source'],
    'batch_without_terminator_blocks' => $blockedBatch['blocked_by'] === 'parse_args',
    'batch_without_terminator_error' => $blockedBatch['parse_args']['error_message'],
    'batch_with_terminator_parse_args_success' => $allowedBatch['parse_args']['parse_args_success'],
    'batch_terminator_seen' => $allowedBatch['parse_args']['end_of_options_terminator_seen'],
    'batch_option_like_folder_admitted' => $allowedBatch['arguments']['in_folder'] === '--wp-source',
    'batch_option_like_tokens_after_terminator_are_positionals' => $allowedBatch['arguments']['end_of_options_boundary']['option_like_tokens_after_terminator_are_positionals'],
    'single_without_terminator_blocks' => $blockedSingle['blocked_by'] === 'parse_args',
    'single_without_terminator_error' => $blockedSingle['parse_args']['error_message'],
    'single_with_terminator_parse_args_success' => $allowedSingle['parse_args']['parse_args_success'],
    'single_terminator_seen' => $allowedSingle['parse_args']['end_of_options_terminator_seen'],
    'single_option_like_filename_admitted' => $allowedSingle['arguments']['filename'] === '--wp-import.pdf',
    'single_option_like_tokens_after_terminator_are_positionals' => $allowedSingle['arguments']['end_of_options_boundary']['option_like_tokens_after_terminator_are_positionals'],
    'filesystem_touched_before_terminator_handling' => $allowedBatch['arguments']['end_of_options_boundary']['filesystem_touched_before_terminator_handling']
        || $allowedSingle['arguments']['end_of_options_boundary']['filesystem_touched_before_terminator_handling'],
    'executes_python_or_models' => $allowedBatch['executes_python_or_models'] || $allowedSingle['executes_python_or_models'],
    'executes_multiprocessing' => $allowedBatch['executes_multiprocessing'] || $allowedSingle['executes_multiprocessing'],
    'executes_external_pdf_tools' => $allowedBatch['executes_external_pdf_tools'] || $allowedSingle['executes_external_pdf_tools'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
