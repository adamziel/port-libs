<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\TableFormatter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = [
    'pnum' => 0,
    'bbox' => [0.0, 0.0, 600.0, 800.0],
    'layout' => [
        'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
        'bboxes' => [
            ['label' => 'Table', 'bbox' => [120.0, 220.0, 560.0, 420.0]],
        ],
    ],
    'blocks' => [
        [
            'type' => 'Text',
            'bbox' => [60.0, 60.0, 280.0, 92.0],
            'lines' => [
                ['text' => 'Migration table follows.', 'bbox' => [60.0, 64.0, 260.0, 80.0]],
            ],
        ],
        [
            'type' => 'Table',
            'bbox' => [60.0, 110.0, 280.0, 210.0],
            'lines' => [
                ['text' => 'Status Intro Published Media Draft', 'bbox' => [62.0, 116.0, 270.0, 136.0]],
            ],
        ],
    ],
];

$markdownTable = "| Block | Status |\n| --- | --- |\n| Intro | Published |\n| Media | Draft |";
$formatted = (new TableFormatter())->formatTables([$page], [$markdownTable]);
$merged = (new MarkdownPostProcessor())->mergeBlocks($formatted['pages']);

foreach ($merged as $block) {
    if (($block['block_type'] ?? '') !== 'Table') {
        continue;
    }

    $rows = array_values(array_filter(
        preg_split('/\R/', trim($block['text'])) ?: [],
        static fn (string $row): bool => trim($row, " \t|") !== '' && !preg_match('/^\s*\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)+\|?\s*$/', $row)
    ));

    echo "<!-- wp:table -->\n";
    echo '<figure class="wp-block-table"><table><tbody>';
    foreach ($rows as $row) {
        $cells = array_map(
            static fn (string $cell): string => htmlspecialchars(trim($cell), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            explode('|', trim($row, " \t|"))
        );
        echo '<tr><td>' . implode('</td><td>', $cells) . '</td></tr>';
    }
    echo "</tbody></table></figure>\n";
    echo "<!-- /wp:table -->\n";
}
