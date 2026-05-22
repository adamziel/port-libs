<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\TableFormatter;
use PortLibs\MarkerPDF\TableRecognizer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = [
    'pnum' => 7,
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
                ['text' => 'Content inventory table follows.', 'bbox' => [60.0, 64.0, 260.0, 80.0]],
            ],
        ],
        [
            'type' => 'Table',
            'bbox' => [60.0, 110.0, 280.0, 210.0],
            'lines' => [
                ['text' => 'Old table extraction text', 'bbox' => [62.0, 116.0, 270.0, 136.0]],
            ],
        ],
    ],
];

$suppliedRecognition = [
    'rows' => [
        ['row_id' => 0, 'bbox' => [0.0, 0.0, 240.0, 30.0]],
        ['row_id' => 1, 'bbox' => [0.0, 40.0, 240.0, 70.0]],
        ['row_id' => 2, 'bbox' => [0.0, 80.0, 240.0, 110.0]],
    ],
    'cols' => [
        ['col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 120.0]],
        ['col_id' => 1, 'bbox' => [120.0, 0.0, 240.0, 120.0]],
    ],
    'cells' => [
        ['bbox' => [10.0, 5.0, 230.0, 25.0], 'text' => 'Block audit'],
        ['bbox' => [10.0, 45.0, 90.0, 65.0], 'text' => 'Intro'],
        ['bbox' => [130.0, 45.0, 230.0, 65.0], 'text' => 'Published'],
        ['bbox' => [10.0, 85.0, 90.0, 105.0], 'text' => 'Media .... 24'],
        ['bbox' => [130.0, 85.0, 230.0, 105.0], 'text' => "Needs\nReview"],
    ],
];

$recognizer = new TableRecognizer();
$formattedRecognition = $recognizer->formatRecognizedTables([$suppliedRecognition], [['width' => 1200, 'height' => 1600]]);
$spanMetadata = [];
foreach ($formattedRecognition['assigned_cells'][0] as $cell) {
    if (count($cell['row_ids']) <= 1 && count($cell['col_ids']) <= 1) {
        continue;
    }

    $spanMetadata[] = [
        'text' => $cell['text'],
        'row_ids' => $cell['row_ids'],
        'col_ids' => $cell['col_ids'],
    ];
}
$spanByText = [];
foreach ($spanMetadata as $span) {
    $spanByText[$span['text']] = $span;
}
$formattedPages = (new TableFormatter())->formatTables([$page], $formattedRecognition['markdown_tables']);
$merged = (new MarkdownPostProcessor())->mergeBlocks($formattedPages['pages']);

foreach ($merged as $block) {
    if (($block['block_type'] ?? '') !== 'Table') {
        continue;
    }

    echo '<!-- markerpdf:table-spans ' . json_encode($spanMetadata, JSON_UNESCAPED_SLASHES) . " -->\n";
    $rows = array_values(array_filter(
        preg_split('/\R/', trim($block['text'])) ?: [],
        static fn (string $row): bool => trim($row, " \t|") !== '' && !preg_match('/^\s*\|-+\|-+\|?\s*$/', $row)
    ));

    echo "<!-- wp:table -->\n";
    echo '<figure class="wp-block-table"><table><tbody>';
    foreach ($rows as $row) {
        $cells = array_map(
            static fn (string $cell): string => htmlspecialchars(trim(str_replace('\\-', '-', $cell)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            explode('|', trim($row, " \t|"))
        );
        echo '<tr>';
        foreach ($cells as $cell) {
            $span = $spanByText[$cell] ?? null;
            $attrs = '';
            if ($span !== null && count($span['col_ids']) > 1) {
                $attrs .= ' colspan="' . count($span['col_ids']) . '"';
            }
            if ($span !== null && count($span['row_ids']) > 1) {
                $attrs .= ' rowspan="' . count($span['row_ids']) . '"';
            }
            echo '<td' . $attrs . '>' . $cell . '</td>';
        }
        echo '</tr>';
    }
    echo "</tbody></table></figure>\n";
    echo "<!-- /wp:table -->\n";
}
