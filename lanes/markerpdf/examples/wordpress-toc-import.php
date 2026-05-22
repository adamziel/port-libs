<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\HeadingCleaner;
use PortLibs\MarkerPDF\MarkdownPostProcessor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pages = [
    [
        'pnum' => 0,
        'layout_boxes' => [
            ['label' => 'Title', 'bbox' => [70.0, 40.0, 360.0, 66.0]],
            ['label' => 'Section-header', 'bbox' => [72.0, 114.0, 330.0, 136.0]],
        ],
        'blocks' => [
            [
                'type' => 'Text',
                'lines' => [
                    ['text' => 'migration runbook', 'bbox' => [72.0, 42.0, 358.0, 64.0], 'height' => 26.0],
                    ['text' => 'This PDF will become a draft import guide.', 'bbox' => [72.0, 82.0, 390.0, 94.0], 'height' => 12.0],
                    ['text' => 'content checks', 'bbox' => [72.0, 116.0, 328.0, 134.0], 'height' => 18.0],
                    ['text' => 'Confirm headings before publishing.', 'bbox' => [72.0, 152.0, 390.0, 164.0], 'height' => 12.0],
                ],
            ],
        ],
    ],
    [
        'pnum' => 1,
        'blocks' => [
            ['type' => 'Section-header', 'lines' => [['text' => 'media cleanup', 'height' => 18.0]]],
        ],
    ],
    [
        'pnum' => 2,
        'blocks' => [
            ['type' => 'Section-header', 'lines' => [['text' => 'publish checklist', 'height' => 18.0]]],
        ],
    ],
];

$headings = new HeadingCleaner();
$processor = new MarkdownPostProcessor();
$pages = $headings->inferHeadingLevels($headings->splitHeadingBlocks($pages));
$toc = $headings->computeToc($pages);

echo "<!-- wp:list -->\n<ul>\n";
foreach ($toc as $item) {
    $title = htmlspecialchars($item['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $level = $item['level'] ?? 2;
    echo '<li data-marker-heading-level="' . $level . '">' . $title . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n\n";

foreach ($pages as $page) {
    foreach ($page['blocks'] as $block) {
        if (!in_array($block['type'] ?? 'Text', ['Title', 'Section-header'], true)) {
            continue;
        }
        $text = $headings->computeToc([['pnum' => $page['pnum'], 'blocks' => [$block]]])[0]['title'];
        echo trim($processor->surroundBlock($text, $block['type'], $block['heading_level'] ?? null)) . "\n\n";
    }
}
