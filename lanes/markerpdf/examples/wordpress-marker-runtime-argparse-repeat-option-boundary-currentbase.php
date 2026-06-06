<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$plan = (new BatchConverter())->runtimeMainArgumentPreflightPlan([
    '--metadata_file',
    'stale-wordpress-metadata.json',
    '--workers',
    '2',
    '--min_length',
    '250',
    '--metadata_file=current-wordpress-metadata.json',
    '--workers=4',
    '--min_length=0',
    '/wp/uploads/marker-batch',
    '/wp/marker-output',
]);

if ($plan['parse_args']['parse_args_success'] !== true) {
    throw new RuntimeException('Expected repeated runtime argparse options to parse successfully.');
}
if (($plan['arguments']['options']['metadata_file'] ?? null) !== 'current-wordpress-metadata.json') {
    throw new RuntimeException('Expected repeated --metadata_file to keep the last upstream argparse value.');
}
if (($plan['arguments']['options']['workers'] ?? null) !== 4) {
    throw new RuntimeException('Expected repeated --workers to keep the last upstream argparse value.');
}
if (($plan['arguments']['options']['min_length'] ?? null) !== 0) {
    throw new RuntimeException('Expected repeated --min_length=0 to override the stale positive value.');
}
if (($plan['arguments']['last_occurrence_wins'] ?? null) !== true) {
    throw new RuntimeException('Expected repeated option review to record last-occurrence-wins semantics.');
}
if (($plan['semantic_boundaries']['repeated_options_last_occurrence_wins'] ?? null) !== true) {
    throw new RuntimeException('Expected repeated option semantic boundary to be visible.');
}
if ($plan['parse_args']['filesystem_touched_before_error'] !== false) {
    throw new RuntimeException('Repeated option review must stay before filesystem runtime work.');
}
if ($plan['executes_python_or_models'] || $plan['executes_multiprocessing'] || $plan['executes_external_pdf_tools']) {
    throw new RuntimeException('Repeated option smoke must not launch Python, models, multiprocessing, or external PDF tools.');
}

echo json_encode([
    'scenario' => 'wordpress-marker-runtime-argparse-repeat-option-boundary-currentbase',
    'purpose' => 'Review repeated convert.py CLI options before WordPress PDF queues touch uploads, metadata JSON, model handoff, multiprocessing, or external PDF tools.',
    'source' => $plan['source'],
    'parse_success' => $plan['parse_args']['parse_args_success'],
    'repeated_options' => $plan['arguments']['repeated_options'],
    'repeated_option_counts' => $plan['arguments']['repeated_option_counts'],
    'last_occurrence_wins' => $plan['arguments']['last_occurrence_wins'],
    'metadata_file_final' => $plan['arguments']['options']['metadata_file'],
    'workers_final' => $plan['arguments']['options']['workers'],
    'min_length_final' => $plan['arguments']['options']['min_length'],
    'metadata_file_occurrences' => $plan['arguments']['option_occurrences']['metadata_file'],
    'workers_occurrences' => $plan['arguments']['option_occurrences']['workers'],
    'min_length_occurrences' => $plan['arguments']['option_occurrences']['min_length'],
    'stale_metadata_file_excluded' => $plan['arguments']['options']['metadata_file'] !== 'stale-wordpress-metadata.json',
    'stale_min_length_excluded' => $plan['arguments']['options']['min_length'] !== 250,
    'metadata_file_truthy_for_json_load' => $plan['semantic_boundaries']['metadata_file_truthy_for_json_load'],
    'repeated_options_last_occurrence_wins' => $plan['semantic_boundaries']['repeated_options_last_occurrence_wins'],
    'next_stage' => $plan['next_stage'],
    'filesystem_touched_before_error' => $plan['parse_args']['filesystem_touched_before_error'],
    'executes_python_or_models' => $plan['executes_python_or_models'],
    'executes_multiprocessing' => $plan['executes_multiprocessing'],
    'executes_external_pdf_tools' => $plan['executes_external_pdf_tools'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
