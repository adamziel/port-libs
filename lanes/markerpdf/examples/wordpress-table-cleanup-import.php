<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\TableUtils;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$cellBlocks = [
    ['text' => "Status\nReview", 'bbox' => [140.0, 10.0, 220.0, 20.0]],
    ['text' => 'Media .... 24', 'bbox' => [40.0, 32.0, 110.0, 42.0]],
    ['text' => 'Draft', 'bbox' => [140.0, 31.0, 220.0, 41.0]],
    ['text' => 'Block', 'bbox' => [40.0, 12.0, 110.0, 22.0]],
];

$utils = new TableUtils();
$cells = array_map(
    static fn (array $block): string => $utils->replaceNewlines($utils->replaceDots((string) $block['text'])),
    $utils->sortTableBlocks($cellBlocks)
);

echo "<!-- wp:table -->\n";
echo '<figure class="wp-block-table"><table><tbody>';
foreach (array_chunk($cells, 2) as $row) {
    $escaped = array_map(
        static fn (string $cell): string => htmlspecialchars($cell, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        $row
    );
    echo '<tr><td>' . implode('</td><td>', $escaped) . '</td></tr>';
}
echo "</tbody></table></figure>\n";
echo "<!-- /wp:table -->\n";
