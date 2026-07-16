<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\LayoutOrderer;
use PortLibs\MarkerPDF\MarkdownPostProcessor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pages = [
    [
        'bbox' => [0.0, 0.0, 600.0, 800.0],
        'order' => [
            'image_bbox' => [0.0, 0.0, 600.0, 800.0],
            'bboxes' => [
                ['position' => 0, 'bbox' => [60.0, 24.0, 560.0, 46.0]],
                ['position' => 1, 'bbox' => [60.0, 90.0, 280.0, 210.0]],
                ['position' => 2, 'bbox' => [320.0, 90.0, 560.0, 210.0]],
                ['position' => 9, 'bbox' => [60.0, 756.0, 560.0, 790.0]],
            ],
        ],
        'blocks' => [
            [
                'type' => 'Text',
                'lines' => [
                    ['text' => 'Second column lists media items for review.', 'bbox' => [330.0, 104.0, 540.0, 120.0]],
                ],
            ],
            [
                'type' => 'Page-footer',
                'lines' => [
                    ['text' => 'Internal migration draft', 'bbox' => [72.0, 764.0, 520.0, 784.0]],
                ],
            ],
            [
                'type' => 'Page-header',
                'lines' => [
                    ['text' => 'Two-column PDF export', 'bbox' => [72.0, 28.0, 520.0, 42.0]],
                ],
            ],
            [
                'type' => 'Text',
                'lines' => [
                    ['text' => 'First column introduces the WordPress import.', 'bbox' => [72.0, 104.0, 260.0, 120.0]],
                ],
            ],
        ],
    ],
];

$orderer = new LayoutOrderer();
$processor = new MarkdownPostProcessor();
$pages = $orderer->sortBlocksInReadingOrder($pages);
$blocks = $processor->mergeBlocks($pages);

foreach ($blocks as $block) {
    if (($block['block_type'] ?? '') !== 'Text') {
        continue;
    }

    foreach (preg_split('/\n{2,}/', trim($block['text'])) ?: [] as $paragraph) {
        if ($paragraph === '') {
            continue;
        }
        echo "<!-- wp:paragraph -->\n";
        echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
        echo "<!-- /wp:paragraph -->\n\n";
    }
}
