<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BlockSpanFilter;
use PortLibs\MarkerPDF\MarkdownPostProcessor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = [
    'pnum' => 8,
    'blocks' => [
        [
            'block_type' => 'Text',
            'bbox' => [72.0, 96.0, 520.0, 160.0],
            'lines' => [
                ['bbox' => [72.0, 96.0, 520.0, 108.0], 'spans' => []],
                ['bbox' => [72.0, 112.0, 520.0, 124.0], 'spans' => [
                    ['span_id' => 'body_8_0', 'font' => 'Body', 'text' => 'Only live spans become imported content.'],
                ]],
                ['bbox' => [72.0, 132.0, 520.0, 144.0], 'spans' => []],
            ],
        ],
    ],
];

$filtered = (new BlockSpanFilter())->filterPage($page);
$mergedPages = (new MarkdownPostProcessor())->mergeSpans([$filtered]);
$blocks = (new MarkdownPostProcessor())->mergeBlocks($mergedPages);

echo '<!-- markerpdf:empty-line-filter ' . htmlspecialchars(json_encode([
    'pnum' => $page['pnum'],
    'source_lines' => count($page['blocks'][0]['lines']),
    'kept_lines' => count($filtered['blocks'][0]['lines']),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($blocks as $block) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars(trim($block['text']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n";
}
