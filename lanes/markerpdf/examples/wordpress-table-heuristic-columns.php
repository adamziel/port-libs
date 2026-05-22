<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\TableRecognizer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$tableRows = [
    ['Block', 'Import status', 'Owner'],
    ['Posts', 'Ready', 'Editor'],
    ['Pages', 'Needs review', 'Editor'],
    ['Media', 'Queued', 'Media lead'],
    ['Menus', 'Ready', 'Site admin'],
    ['Forms', 'Needs mapping', 'Developer'],
    ['Products', 'Queued', 'Store manager'],
    ['Users', 'Ready', 'Site admin'],
];

$geometryRows = [];
$cells = [];
foreach ($tableRows as $rowIndex => $row) {
    $jitter = $rowIndex < 4 ? 0.0 : 2.0;
    $top = 10.0 + ($rowIndex * 30.0);
    $bottom = $top + 16.0;
    $rowCells = [
        ['bbox' => [10.0 + $jitter, $top, 70.0 + $jitter, $bottom], 'text' => $row[0]],
        ['bbox' => [210.0 + $jitter, $top, 270.0 + $jitter, $bottom], 'text' => $row[1]],
        ['bbox' => [410.0 + $jitter, $top, 470.0 + $jitter, $bottom], 'text' => $row[2]],
    ];
    $geometryRows[] = $rowCells;
    array_push($cells, ...$rowCells);
}

$recognizer = new TableRecognizer();
$imageSize = ['width' => 1000, 'height' => 800];
$separators = array_map(
    static fn (float $separator): float => round($separator, 3),
    $recognizer->heuristicColumnSeparators($geometryRows, $imageSize)
);
$assigned = $recognizer->assignRowsColumns(['cells' => $cells], $imageSize);
$markdown = $recognizer->markdownFormat($assigned);

echo '<!-- markerpdf:tabled-heuristic-column-separators ' . json_encode($separators, JSON_UNESCAPED_SLASHES) . " -->\n";

$rows = array_values(array_filter(
    preg_split('/\R/', trim($markdown)) ?: [],
    static fn (string $row): bool => trim($row, " \t|") !== '' && !preg_match('/^\s*\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)+\|?\s*$/', $row)
));

echo "<!-- wp:table -->\n";
echo '<figure class="wp-block-table"><table><tbody>';
foreach ($rows as $row) {
    $htmlCells = array_map(
        static fn (string $cell): string => htmlspecialchars(trim(str_replace('\\-', '-', $cell)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        explode('|', trim($row, " \t|"))
    );
    echo '<tr><td>' . implode('</td><td>', $htmlCells) . '</td></tr>';
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
