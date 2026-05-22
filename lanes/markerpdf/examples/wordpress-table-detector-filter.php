<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\TableRecognizer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rawDetectorCells = [
    ['bbox' => [18.0, 12.0, 18.0, 36.0], 'text' => null],
    ['bbox' => [20.0, 20.0, 180.0, 48.0], 'text' => null],
    ['bbox' => [190.0, 20.0, 320.0, 48.0], 'text' => null],
    ['bbox' => [20.0, 70.0, 180.0, 98.0], 'text' => null],
    ['bbox' => [190.0, 70.0, 320.0, 98.0], 'text' => null],
    ['bbox' => [190.0, 110.0, 320.0, 110.0], 'text' => null],
];

$recognizer = new TableRecognizer();
$cells = $recognizer->getCells(
    [[0.0, 0.0, 340.0, 120.0]],
    [['width' => 400, 'height' => 200]],
    [null],
    [$rawDetectorCells]
);

$recognized = $recognizer->recognizeTables(
    $cells['table_cells'],
    $cells['needs_ocr'],
    [[
        'rows' => [
            ['row_id' => 0, 'bbox' => [0.0, 0.0, 340.0, 55.0]],
            ['row_id' => 1, 'bbox' => [0.0, 60.0, 340.0, 110.0]],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox' => [0.0, 0.0, 185.0, 120.0]],
            ['col_id' => 1, 'bbox' => [185.0, 0.0, 340.0, 120.0]],
        ],
    ]],
    [[['text' => 'Field'], ['text' => 'Value'], ['text' => 'Title'], ['text' => 'Published']]]
);

$formatted = $recognizer->formatRecognizedTables($recognized, [['width' => 400, 'height' => 200]]);
$droppedCells = count($rawDetectorCells) - count($cells['table_cells'][0]);

echo '<!-- markerpdf:detector-filter ' . json_encode([
    'raw_detector_cells' => count($rawDetectorCells),
    'usable_cells' => count($cells['table_cells'][0]),
    'dropped_zero_area_cells' => $droppedCells,
], JSON_UNESCAPED_SLASHES) . " -->\n";

$rows = array_values(array_filter(
    preg_split('/\R/', trim($formatted['markdown_tables'][0])) ?: [],
    static fn (string $row): bool => trim($row, " \t|") !== '' && !preg_match('/^\s*\|-+\|-+\|?\s*$/', $row)
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
