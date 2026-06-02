<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\TableRecognizer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$table = [
    'rows' => [
        ['row_id' => 0, 'bbox' => [0.0, 0.0, 300.0, 25.0]],
        ['row_id' => 1, 'bbox' => [0.0, 35.0, 300.0, 60.0]],
        ['row_id' => 2, 'bbox' => [0.0, 85.0, 300.0, 110.0]],
    ],
    'cols' => [
        ['col_id' => 0, 'bbox' => [0.0, 0.0, 95.0, 120.0]],
        ['col_id' => 1, 'bbox' => [105.0, 0.0, 195.0, 120.0]],
        ['col_id' => 2, 'bbox' => [205.0, 0.0, 300.0, 120.0]],
    ],
    'cells' => [
        ['bbox' => [5.0, 5.0, 295.0, 20.0], 'text' => 'Inventory summary'],
        ['bbox' => [5.0, 36.0, 92.0, 109.0], 'text' => 'Media group'],
        ['bbox' => [110.0, 39.0, 190.0, 56.0], 'text' => 'Image count'],
        ['bbox' => [210.0, 39.0, 290.0, 56.0], 'text' => '12'],
        ['bbox' => [110.0, 89.0, 190.0, 106.0], 'text' => 'Review state'],
        ['bbox' => [210.0, 89.0, 290.0, 106.0], 'text' => 'Needs review'],
    ],
];

$recognizer = new TableRecognizer();
$assigned = $recognizer->assignRowsColumns($table, ['width' => 300, 'height' => 120]);
$geometry = $recognizer->mergedCellGeometry($assigned, $table['rows'], $table['cols']);

$byAnchor = [];
$covered = [];
foreach ($geometry as $span) {
    $anchorKey = $span['anchor']['row_id'] . ':' . $span['anchor']['col_id'];
    $byAnchor[$anchorKey] = $span;
    foreach ($span['grid_cells'] as $gridCell) {
        $key = $gridCell['row_id'] . ':' . $gridCell['col_id'];
        if ($key !== $anchorKey) {
            $covered[$key] = true;
        }
    }
}

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

echo '<!-- markerpdf:table-merged-cell-geometry ' . json_encode($geometry, JSON_UNESCAPED_SLASHES) . " -->\n";
echo "<!-- markerpdf:executes_python_or_models false -->\n";
echo "<!-- wp:table -->\n";
echo '<figure class="wp-block-table"><table><tbody>';
foreach (array_keys($rows) as $row) {
    echo '<tr>';
    foreach (array_keys($cols) as $col) {
        $key = $row . ':' . $col;
        if (isset($covered[$key])) {
            continue;
        }

        $attrs = '';
        $span = $byAnchor[$key] ?? null;
        if ($span !== null && $span['colspan'] > 1) {
            $attrs .= ' colspan="' . $span['colspan'] . '"';
        }
        if ($span !== null && $span['rowspan'] > 1) {
            $attrs .= ' rowspan="' . $span['rowspan'] . '"';
        }

        echo '<td' . $attrs . '>' . htmlspecialchars($cellText[$key] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</td>';
    }
    echo '</tr>';
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
