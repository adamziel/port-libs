<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\TableRecognizer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$recognizer = new TableRecognizer();
$assigned = $recognizer->assignRowsColumns(
    [
        'rows' => [
            ['row_id' => 0, 'bbox' => [0.0, 0.0, 300.0, 30.0]],
            ['row_id' => 1, 'bbox' => [0.0, 34.0, 300.0, 56.0]],
            ['row_id' => 2, 'bbox' => [0.0, 95.0, 300.0, 125.0]],
        ],
        'cols' => [
            ['col_id' => 0, 'bbox' => [0.0, 0.0, 95.0, 130.0]],
            ['col_id' => 1, 'bbox' => [100.0, 0.0, 195.0, 130.0]],
            ['col_id' => 2, 'bbox' => [200.0, 0.0, 300.0, 130.0]],
        ],
        'cells' => [
            ['bbox' => [8.0, 5.0, 88.0, 24.0], 'text' => 'Block'],
            ['bbox' => [108.0, 5.0, 188.0, 24.0], 'text' => 'Import note'],
            ['bbox' => [208.0, 5.0, 288.0, 24.0], 'text' => 'State'],
            ['bbox' => [112.0, 38.0, 192.0, 53.0], 'text' => 'wrapped line'],
            ['bbox' => [8.0, 100.0, 88.0, 119.0], 'text' => 'Media'],
            ['bbox' => [108.0, 100.0, 188.0, 119.0], 'text' => 'Ready'],
            ['bbox' => [208.0, 100.0, 288.0, 119.0], 'text' => 'Published'],
        ],
    ],
    ['width' => 300, 'height' => 130]
);

$rowIds = [];
foreach ($assigned as $cell) {
    $rowIds[(int) $cell['row_ids'][0]] = true;
}

echo '<!-- markerpdf:multiline-table ' . json_encode([
    'model_rows' => 3,
    'rendered_rows' => count($rowIds),
    'merged_wrapped_rows' => 1,
], JSON_UNESCAPED_SLASHES) . " -->\n";

$rows = array_values(array_filter(
    preg_split('/\R/', trim($recognizer->markdownFormat($assigned))) ?: [],
    static fn (string $row): bool => trim($row, " \t|") !== '' && !preg_match('/^\s*\|(?:-+\|)+\s*$/', $row)
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
