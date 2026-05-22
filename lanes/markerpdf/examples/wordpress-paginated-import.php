<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pages = [
    [
        'pnum' => 0,
        'blocks' => [
            [
                'type' => 'Title',
                'heading_level' => 1,
                'lines' => [
                    ['text' => 'migration packet', 'bbox' => [72.0, 40.0, 270.0, 64.0]],
                ],
            ],
            [
                'type' => 'Text',
                'lines' => [
                    ['text' => 'Imported PDFs often carry page boundaries.', 'bbox' => [72.0, 90.0, 330.0, 102.0]],
                    ['text' => 'WordPress imports should keep those boundaries reviewable.', 'bbox' => [72.0, 118.0, 420.0, 130.0]],
                ],
            ],
        ],
    ],
    [
        'pnum' => 1,
        'blocks' => [
            [
                'type' => 'List-item',
                'lines' => [
                    ['text' => '- Confirm page breaks', 'bbox' => [90.0, 74.0, 260.0, 86.0]],
                    ['text' => '- Review imported headings', 'bbox' => [90.0, 118.0, 280.0, 130.0]],
                ],
            ],
        ],
    ],
];

$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($pages, paginateOutput: true);

foreach ($blocks as $block) {
    if ($block['page_start']) {
        echo '<!-- wp:separator {"className":"markerpdf-page-break","metadata":{"name":"PDF page ' . ((int) $block['pnum'] + 1) . '"}} -->' . "\n";
        echo '<hr class="wp-block-separator has-alpha-channel-opacity markerpdf-page-break"/>' . "\n";
        echo "<!-- /wp:separator -->\n\n";
        continue;
    }

    if (in_array($block['block_type'], ['Title', 'Section-header'], true)) {
        $level = strspn($block['text'], '#');
        $level = max(1, min(6, $level > 0 ? $level : 2));
        $text = trim(ltrim($block['text'], '# '));
        echo '<!-- wp:heading {"level":' . $level . '} -->' . "\n";
        echo '<h' . $level . '>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h' . $level . ">\n";
        echo "<!-- /wp:heading -->\n\n";
        continue;
    }

    if ($block['block_type'] === 'List-item') {
        echo "<!-- wp:list -->\n<ul>\n";
        foreach (preg_split('/\R+/', trim($block['text'])) ?: [] as $line) {
            $item = preg_replace('/^\s*-\s*/', '', $line) ?? $line;
            echo '<li>' . htmlspecialchars($item, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
        }
        echo "</ul>\n<!-- /wp:list -->\n\n";
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
