<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\LayoutAnnotator;
use PortLibs\MarkerPDF\MarkdownPostProcessor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = [
    'pnum' => 0,
    'bbox' => [0.0, 0.0, 600.0, 800.0],
    'text_lines' => [
        'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
        'bboxes' => [
            ['bbox' => [120.0, 80.0, 1000.0, 140.0]],
            ['bbox' => [120.0, 200.0, 1000.0, 260.0]],
        ],
    ],
    'blocks' => [
        [
            'bbox' => [60.0, 42.0, 500.0, 54.0],
            'lines' => [
                ['text' => 'migration packet', 'bbox' => [60.0, 42.0, 500.0, 54.0]],
            ],
        ],
        [
            'bbox' => [60.0, 102.0, 420.0, 126.0],
            'lines' => [
                ['text' => 'Review supplied layout output before publishing.', 'bbox' => [60.0, 102.0, 420.0, 126.0]],
            ],
        ],
    ],
];

$suppliedLayout = [
    'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
    'bboxes' => [
        ['label' => 'Title', 'bbox' => [120.0, 80.0, 1000.0, 140.0]],
        ['label' => 'Text', 'bbox' => [120.0, 200.0, 1000.0, 260.0]],
    ],
];

$annotator = new LayoutAnnotator();
$detection = $annotator->runWithSuppliedLayouts(
    ['rendered-page-placeholder'],
    [$page],
    [$suppliedLayout]
);
$annotated = $annotator->annotateBlockTypes($detection['pages']);
$merged = (new MarkdownPostProcessor())->mergeBlocks($annotated);

$blocks = [];
foreach ($merged as $block) {
    if ($block['block_type'] === 'Title') {
        $text = trim(ltrim($block['text'], '# '));
        $blocks[] = [
            'blockName' => 'core/heading',
            'innerHTML' => '<h1>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h1>',
        ];
        continue;
    }

    $blocks[] = [
        'blockName' => 'core/paragraph',
        'innerHTML' => '<p>' . htmlspecialchars(trim($block['text']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>',
    ];
}

echo json_encode([
    'scenario' => 'wordpress-pdf-layout-detection-preflight',
    'layoutPlan' => $detection['plan'],
    'layoutLabels' => array_column($annotated[0]['blocks'], 'block_type'),
    'blocks' => $blocks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
