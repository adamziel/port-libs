<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BlockSpanFilter;
use PortLibs\MarkerPDF\MarkdownPostProcessor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pages = [[
    'pnum' => 4,
    'blocks' => [
        [
            'block_type' => 'Page-header',
            'bbox' => [40.0, 20.0, 320.0, 36.0],
            'lines' => [[
                'bbox' => [40.0, 20.0, 320.0, 36.0],
                'spans' => [['span_id' => 'header_4', 'font' => 'Header', 'text' => 'Migration Packet']],
            ]],
        ],
        [
            'block_type' => 'Text',
            'bbox' => [40.0, 72.0, 520.0, 96.0],
            'lines' => [[
                'bbox' => [40.0, 72.0, 520.0, 96.0],
                'spans' => [['span_id' => 'body_4', 'font' => 'Body', 'text' => 'Imported paragraph for editorial review.']],
            ]],
        ],
        [
            'block_type' => 'Picture',
            'bbox' => [40.0, 120.0, 520.0, 300.0],
            'image_filename' => '4_image_0.png',
            'lines' => [[
                'bbox' => [60.0, 140.0, 400.0, 160.0],
                'spans' => [['span_id' => 'picture_text_4', 'font' => 'Picture', 'text' => 'Screenshot OCR overlay']],
            ]],
        ],
    ],
]];

$filtered = (new BlockSpanFilter())->filterPages($pages, ['header_4']);
$mergedPages = (new MarkdownPostProcessor())->mergeSpans($filtered);
$blocks = (new MarkdownPostProcessor())->mergeBlocks($mergedPages);

foreach ($blocks as $block) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars(trim($block['text']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

foreach ($filtered as $page) {
    foreach ($page['blocks'] as $block) {
        if (($block['block_type'] ?? '') !== 'Picture' || ($block['image_filename'] ?? '') === '') {
            continue;
        }

        echo '<!-- markerpdf:block-span-filter ' . json_encode([
            'pnum' => $page['pnum'],
            'removed_text_spans' => true,
            'bbox' => $block['bbox'],
        ], JSON_UNESCAPED_SLASHES) . " -->\n";
        echo "<!-- wp:image -->\n";
        echo '<figure class="wp-block-image"><img src="' . htmlspecialchars((string) $block['image_filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" alt="" /></figure>' . "\n";
        echo "<!-- /wp:image -->\n\n";
    }
}
