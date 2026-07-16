<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\DebugDataExporter;
use PortLibs\MarkerPDF\MarkerSettings;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$debugRoot = sys_get_temp_dir() . '/markerpdf-wordpress-debug';
$page = [
    'pnum' => 0,
    'bbox' => [0.0, 0.0, 600.0, 800.0],
    'blocks' => [
        [
            'block_type' => 'Title',
            'bbox' => [72.0, 48.0, 420.0, 84.0],
            'lines' => [
                ['text' => 'Migration Brief', 'bbox' => [72.0, 48.0, 420.0, 84.0]],
            ],
        ],
        [
            'block_type' => 'Text',
            'bbox' => [72.0, 112.0, 460.0, 154.0],
            'lines' => [
                ['text' => 'Review bounding boxes before publishing imported content.', 'bbox' => [72.0, 112.0, 460.0, 154.0]],
            ],
        ],
    ],
    'images' => ['0_image_0.png' => 'PNG'],
    'layout' => [
        'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
        'bboxes' => [
            ['label' => 'Title', 'bbox' => [144.0, 96.0, 840.0, 168.0]],
            ['label' => 'Text', 'bbox' => [144.0, 224.0, 920.0, 308.0]],
        ],
        'segmentation_map' => 'model-pixels-not-exported',
    ],
    'text_lines' => [
        'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
        'bboxes' => [
            ['bbox' => [144.0, 96.0, 840.0, 168.0]],
            ['bbox' => [144.0, 224.0, 920.0, 308.0]],
        ],
        'heatmap' => 'model-heatmap-not-exported',
        'affinity_map' => 'model-affinity-not-exported',
    ],
];

$exporter = new DebugDataExporter(new MarkerSettings([
    'DEBUG' => true,
    'DEBUG_DATA_FOLDER' => $debugRoot,
]));
$path = $exporter->dumpBboxDebugData('/uploads/migration-brief.pdf', [$page]);
$payload = $exporter->bboxDebugData([$page]);

echo json_encode([
    'scenario' => 'wordpress-debug-bbox-export',
    'debug_file' => $path,
    'page_count' => count($payload),
    'layout_labels' => array_column($payload[0]['layout']['bboxes'], 'label'),
    'text_line_count' => count($payload[0]['text_lines']['bboxes']),
    'block_types' => array_column($payload[0]['blocks'], 'block_type'),
    'omits_heavy_model_fields' => !str_contains((string) file_get_contents((string) $path), 'segmentation_map')
        && !str_contains((string) file_get_contents((string) $path), 'heatmap'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
