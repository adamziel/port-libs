<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\TableFormatter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = [
    'pnum' => 3,
    'bbox' => [0.0, 0.0, 600.0, 800.0],
    'layout' => [
        'image_bbox' => [0.0, 0.0, 1200.0, 1600.0],
        'bboxes' => [
            ['label' => 'Table', 'bbox' => [100.0, 100.0, 210.0, 200.0]],
            ['label' => 'Table', 'bbox' => [209.0, 100.0, 320.0, 200.0]],
        ],
    ],
    'blocks' => [
        [
            'type' => 'Text',
            'bbox' => [30.0, 20.0, 260.0, 40.0],
            'lines' => [
                ['text' => 'Split PDF table fragments follow.', 'bbox' => [30.0, 24.0, 250.0, 36.0]],
            ],
        ],
        [
            'type' => 'Table',
            'bbox' => [50.0, 50.0, 105.0, 100.0],
            'lines' => [
                ['text' => 'Block Status', 'bbox' => [52.0, 55.0, 104.0, 70.0]],
            ],
        ],
        [
            'type' => 'Table',
            'bbox' => [104.5, 50.0, 160.0, 100.0],
            'lines' => [
                ['text' => 'Owner Notes', 'bbox' => [106.0, 55.0, 158.0, 70.0]],
            ],
        ],
    ],
];

$markdownTable = "| Block | Status | Owner |\n| --- | --- | --- |\n| Intro | Published | Editorial |\n| Media | Needs review | Migration |";
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
