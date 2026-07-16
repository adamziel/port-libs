<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\LayoutOrderer;
use PortLibs\MarkerPDF\MarkdownPostProcessor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = [
    'bbox' => [0.0, 0.0, 600.0, 800.0],
    'layout' => [
        'image_bbox' => [0.0, 0.0, 600.0, 800.0],
        'bboxes' => [
            ['label' => 'Text', 'bbox' => [60.0, 90.0, 280.0, 210.0]],
            ['label' => 'Text', 'bbox' => [320.0, 90.0, 560.0, 210.0]],
            ['label' => 'Page-footer', 'bbox' => [60.0, 756.0, 560.0, 790.0]],
        ],
    ],
    'blocks' => [
        [
            'type' => 'Text',
            'lines' => [
                ['text' => 'Second column lists assets needing media review.', 'bbox' => [330.0, 104.0, 540.0, 120.0]],
            ],
        ],
        [
            'type' => 'Page-footer',
            'lines' => [
                ['text' => 'Internal migration draft', 'bbox' => [72.0, 764.0, 520.0, 784.0]],
            ],
        ],
        [
            'type' => 'Text',
            'lines' => [
                ['text' => 'First column introduces the imported PDF section.', 'bbox' => [72.0, 104.0, 260.0, 120.0]],
            ],
        ],
    ],
];

$suppliedOrder = [
    'image_bbox' => [0.0, 0.0, 600.0, 800.0],
    'bboxes' => [
        ['position' => 1, 'bbox' => [60.0, 90.0, 280.0, 210.0]],
        ['position' => 2, 'bbox' => [320.0, 90.0, 560.0, 210.0]],
        ['position' => 9, 'bbox' => [60.0, 756.0, 560.0, 790.0]],
    ],
];

$orderer = new LayoutOrderer();
$detected = $orderer->runWithSuppliedOrder(
    ['rendered-page-placeholder'],
    [$page],
    [$suppliedOrder]
);
$sorted = $orderer->sortBlocksInReadingOrder($detected['pages']);
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
    'scenario' => 'wordpress-pdf-order-detection-preflight',
    'orderPlan' => $detected['plan'],
    'paragraphs' => $paragraphs,
    'blocks' => array_map(
        static fn (string $paragraph): array => [
            'blockName' => 'core/paragraph',
            'innerHTML' => '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>',
        ],
        $paragraphs
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
