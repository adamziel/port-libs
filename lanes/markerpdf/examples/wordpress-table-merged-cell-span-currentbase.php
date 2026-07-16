<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\TableRecognizer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$table = [
    'rows' => [
        ['row_id' => 0, 'bbox' => [0.0, 0.0, 200.0, 30.0]],
        ['row_id' => 1, 'bbox' => [0.0, 45.0, 200.0, 75.0]],
    ],
    'cols' => [
        ['col_id' => 0, 'bbox' => [0.0, 0.0, 120.0, 90.0]],
        ['col_id' => 1, 'bbox' => [120.0, 0.0, 140.0, 90.0]],
        ['col_id' => 2, 'bbox' => [140.0, 0.0, 200.0, 90.0]],
    ],
    'cells' => [
        ['bbox' => [0.0, 5.0, 190.0, 24.0], 'text' => 'Section note'],
        ['bbox' => [125.0, 50.0, 135.0, 70.0], 'text' => 'Gap marker'],
        ['bbox' => [150.0, 50.0, 190.0, 70.0], 'text' => 'Status'],
    ],
];

$recognizer = new TableRecognizer();
$assigned = $recognizer->assignRowsColumns($table, ['width' => 200, 'height' => 90]);
$geometry = $recognizer->mergedCellGeometry($assigned, $table['rows'], $table['cols']);

$cellText = [];
$rows = [];
$cols = [];
foreach ($assigned as $cell) {
    $row = (int) $cell['row_ids'][0];
    $col = (int) $cell['col_ids'][0];
    $rows[$row] = true;
    $cols[$col] = true;
    $cellText[$row . ':' . $col] = (string) $cell['text'];
}
ksort($rows, SORT_NUMERIC);
ksort($cols, SORT_NUMERIC);

$section = $assigned[0] ?? ['row_ids' => [], 'col_ids' => []];
$metadata = [
    'source_truth' => 'tabled.assignment.handle_rowcol_spans stops span expansion once an active span reaches a non-intersecting row/column band',
    'section_note_col_ids' => $section['col_ids'],
    'discontiguous_span_suppressed' => ($section['col_ids'] === [0]),
    'merged_cell_geometry' => $geometry,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:table-merged-cell-span ' . json_encode($metadata, JSON_UNESCAPED_SLASHES) . " -->\n";
echo "<!-- wp:table -->\n";
echo '<figure class="wp-block-table"><table><tbody>';
foreach (array_keys($rows) as $row) {
    echo '<tr>';
    foreach (array_keys($cols) as $col) {
        echo '<td>' . htmlspecialchars($cellText[$row . ':' . $col] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    }
    echo '</tr>';
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
