<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BlockStructure;
use PortLibs\MarkerPDF\MarkdownPostProcessor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourceBlock = [
    'pnum' => 0,
    'block_type' => 'Text',
    'lines' => [
        [
            'bbox' => [72.0, 88.0, 420.0, 106.0],
            'spans' => [[
                'text' => 'Migration Checklist',
                'span_id' => '0_0',
                'bbox' => [72.0, 88.0, 420.0, 106.0],
            ]],
        ],
        [
            'bbox' => [72.0, 126.0, 510.0, 142.0],
            'spans' => [[
                'text' => 'Review imported images before publishing.',
                'span_id' => '0_1',
                'bbox' => [72.0, 126.0, 510.0, 142.0],
            ]],
        ],
    ],
];

$structure = new BlockStructure();
$splitBlocks = $structure->splitBlockLines($sourceBlock, 1);
$splitBlocks[0]['block_type'] = 'Section-header';
$splitBlocks[0]['heading_level'] = 2;
$splitBlocks[1]['block_type'] = 'Text';

$merged = (new MarkdownPostProcessor())->mergeBlocks([[
    'pnum' => 0,
    'blocks' => $splitBlocks,
]]);

$blocks = [];
foreach ($merged as $block) {
    $text = trim($block['text']);
    if (($block['block_type'] ?? '') === 'Section-header') {
        $level = max(1, min(6, strspn($text, '#') ?: 2));
        $text = trim(ltrim($text, '# '));
        $blocks[] = [
            'blockName' => 'core/heading',
            'attrs' => ['level' => $level],
            'innerHTML' => '<h' . $level . '>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h' . $level . '>',
        ];
        continue;
    }

    $blocks[] = [
        'blockName' => 'core/paragraph',
        'innerHTML' => '<p>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>',
    ];
}

echo json_encode([
    'scenario' => 'wordpress-block-line-split-import',
    'sourceMinLineStart' => $structure->getMinLineStart($sourceBlock),
    'splitCount' => count($splitBlocks),
    'splitBboxes' => array_column($splitBlocks, 'bbox'),
    'blocks' => $blocks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
