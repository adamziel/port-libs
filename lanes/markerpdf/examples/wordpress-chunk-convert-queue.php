<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\ChunkConversionPlanner;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$planner = new ChunkConversionPlanner();
$plan = $planner->planFromEnvironment(
    '/var/www/html/wp-content/uploads/pdf-import',
    '/var/www/html/wp-content/uploads/marker-output',
    [
        'NUM_DEVICES' => '2',
        'NUM_WORKERS' => '3',
        'METADATA_FILE' => '/var/www/html/wp-content/uploads/pdf-import/metadata.json',
        'MIN_LENGTH' => '100',
    ]
);

$queueItems = array_map(static fn (array $job): array => [
    'hook' => 'markerpdf_convert_chunk',
    'chunk_idx' => $job['chunk_idx'],
    'num_chunks' => $job['num_chunks'],
    'workers' => $job['workers'],
    'cuda_visible_devices' => $job['env']['CUDA_VISIBLE_DEVICES'],
    'argv' => $job['argv'],
], $plan['jobs']);

echo json_encode([
    'scenario' => 'wordpress-markerpdf-chunk-convert-queue',
    'purpose' => 'Plan chunk_convert.py/chunk_convert.sh-style PDF import shards for a WordPress queue without executing the upstream marker subprocess.',
    'launch_delay_seconds' => $plan['launch_delay_seconds'],
    'jobs' => $queueItems,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
