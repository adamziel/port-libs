<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\LayoutOrderer;
use PortLibs\MarkerPDF\MarkdownPostProcessor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = [
    'bbox' => [0.0, 0.0, 200.0, 160.0],
    'rotation' => 90,
    'pdf_page_bbox' => [20.0, 40.0, 180.0, 240.0],
    'block_bbox_coordinate_space' => 'pdf_page_user_space',
    'order' => [
        'image_bbox' => [0.0, 0.0, 200.0, 160.0],
        'bboxes' => [
            ['position' => 1, 'bbox' => [20.0, 10.0, 40.0, 130.0]],
            ['position' => 2, 'bbox' => [110.0, 10.0, 130.0, 130.0]],
        ],
    ],
    'blocks' => [
        [
            'type' => 'Text',
            'bbox' => [30.0, 150.0, 150.0, 170.0],
            'lines' => [
                ['text' => 'Second rotated column lists media checks.', 'bbox' => [30.0, 150.0, 150.0, 170.0]],
            ],
        ],
        [
            'type' => 'Text',
            'bbox' => [30.0, 60.0, 150.0, 80.0],
            'lines' => [
                ['text' => 'First rotated column introduces the import.', 'bbox' => [30.0, 60.0, 150.0, 80.0]],
            ],
        ],
    ],
];

$orderer = new LayoutOrderer();
$sorted = $orderer->sortBlocksInReadingOrder([$page]);
$merged = (new MarkdownPostProcessor())->mergeBlocks($sorted);

$paragraphs = [];
foreach ($merged as $block) {
    if (($block['block_type'] ?? '') !== 'Text') {
        continue;
    }

    foreach (preg_split('/\n{2,}/', trim($block['text'])) ?: [] as $paragraph) {
        if ($paragraph !== '') {
            $paragraphs[] = $paragraph;
        }
    }
}

echo json_encode([
    'scenario' => 'wordpress-pdf-rotated-reading-order-import',
    'rotation' => $page['rotation'],
    'source_page_bbox' => $page['pdf_page_bbox'],
    'display_page_bbox' => $page['bbox'],
    'block_bbox_coordinate_space' => $page['block_bbox_coordinate_space'],
    'paragraphs' => $paragraphs,
    'blocks' => array_map(
        static fn (string $paragraph): array => [
            'blockName' => 'core/paragraph',
            'innerHTML' => '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>',
        ],
        $paragraphs
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
