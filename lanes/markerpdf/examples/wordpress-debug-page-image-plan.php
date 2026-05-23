<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\DebugPageImagePlanner;
use PortLibs\MarkerPDF\MarkerSettings;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = [
    'pnum' => 0,
    'bbox' => [0.0, 0.0, 600.0, 800.0],
    'blocks' => [
        [
            'block_type' => 'Title',
            'bbox' => [72.0, 48.0, 420.0, 84.0],
            'lines' => [
                ['prelim_text' => 'Migration Brief', 'bbox' => [72.0, 48.0, 420.0, 84.0]],
            ],
        ],
        [
            'block_type' => 'Text',
            'bbox' => [72.0, 112.0, 460.0, 154.0],
            'lines' => [
                ['prelim_text' => 'Review body text before publishing.', 'bbox' => [72.0, 112.0, 460.0, 154.0]],
            ],
        ],
    ],
    'layout' => [
        'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
        'bboxes' => [
            ['label' => 'Title', 'bbox' => [144.0, 96.0, 840.0, 168.0]],
            ['label' => 'Text', 'bbox' => [144.0, 224.0, 920.0, 308.0]],
        ],
    ],
    'text_lines' => [
        'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
        'bboxes' => [
            ['bbox' => [144.0, 96.0, 840.0, 168.0]],
            ['bbox' => [144.0, 224.0, 920.0, 308.0]],
        ],
    ],
];

$planner = new DebugPageImagePlanner(new MarkerSettings([
    'DEBUG' => true,
    'DEBUG_DATA_FOLDER' => sys_get_temp_dir() . '/markerpdf-wordpress-debug',
]));
$plan = $planner->drawPageDebugImagePlans(
    '/uploads/migration-brief.pdf',
    [$page],
    static fn (string $label, int $fontSize): array => [strlen($label) * 6, 11]
);

echo json_encode([
    'scenario' => 'wordpress-debug-page-image-plan',
    'debug_folder' => $plan['debug_folder'],
    'artifact_count' => count($plan['artifacts']),
    'artifacts' => array_map(static fn (array $artifact): array => [
        'type' => $artifact['type'],
        'path' => $artifact['path'],
        'image_size' => $artifact['image_size'],
        'overlay_operations' => count($artifact['operations']),
    ], $plan['artifacts']),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
