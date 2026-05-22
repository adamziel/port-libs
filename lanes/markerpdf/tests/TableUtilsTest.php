<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\TableUtils;

$wordpressTable = static function (array $rows): string {
    $htmlRows = [];
    foreach ($rows as $row) {
        $cells = array_map(
            static fn (string $cell): string => htmlspecialchars($cell, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $row
        );
        $htmlRows[] = '<tr><td>' . implode('</td><td>', $cells) . '</td></tr>';
    }

    return "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table><tbody>"
        . implode('', $htmlRows)
        . "</tbody></table></figure>\n<!-- /wp:table -->\n";
};

return [
    'sorts upstream table blocks into row buckets then left to right' => static function (TestRunner $t): void {
        $blocks = [
            ['text' => 'Status', 'bbox' => [120.0, 12.0, 200.0, 22.0]],
            ['text' => 'Draft', 'bbox' => [120.0, 30.0, 200.0, 40.0]],
            ['text' => 'Block', 'bbox' => [40.0, 10.0, 100.0, 20.0]],
            ['text' => 'Media', 'bbox' => [40.0, 31.0, 100.0, 39.0]],
        ];

        $sorted = (new TableUtils())->sortTableBlocks($blocks);

        $t->same(['Block', 'Status', 'Media', 'Draft'], array_column($sorted, 'text'));
    },
    'uses Python style half-even row rounding from upstream sort_table_blocks' => static function (TestRunner $t): void {
        $blocks = [
            ['text' => 'Right same row', 'bbox' => [100.0, 5.0, 180.0, 15.0]],
            ['text' => 'Next row', 'bbox' => [20.0, 10.0, 80.0, 20.0]],
            ['text' => 'Left same row', 'bbox' => [10.0, 10.0, 80.0, 15.0]],
        ];

        $sorted = (new TableUtils())->sortTableBlocks($blocks, 5.0);

        $t->same(['Left same row', 'Right same row', 'Next row'], array_column($sorted, 'text'));
    },
    'replaces long dot leaders while preserving short ellipses like upstream' => static function (TestRunner $t): void {
        $utils = new TableUtils();

        $t->same('Chapter 4 19', $utils->replaceDots('Chapter 4 .... 19'));
        $t->same('Keep ... ellipsis', $utils->replaceDots('Keep ... ellipsis'));
        $t->same('A B', $utils->replaceDots('A . . . . B'));
    },
    'replaces table cell newlines with spaces and trims edges' => static function (TestRunner $t): void {
        $utils = new TableUtils();

        $t->same('First line Second line Third line', $utils->replaceNewlines("\nFirst line\r\nSecond line\n\nThird line\n"));
    },
    'renders a WordPress table after upstream table utility cleanup' => static function (TestRunner $t) use ($wordpressTable): void {
        $utils = new TableUtils();
        $blocks = [
            ['text' => "Status\nReview", 'bbox' => [140.0, 10.0, 220.0, 20.0]],
            ['text' => 'Media .... 24', 'bbox' => [40.0, 32.0, 110.0, 42.0]],
            ['text' => 'Draft', 'bbox' => [140.0, 31.0, 220.0, 41.0]],
            ['text' => 'Block', 'bbox' => [40.0, 12.0, 110.0, 22.0]],
        ];

        $cells = array_map(
            static fn (array $block): string => $utils->replaceNewlines($utils->replaceDots((string) $block['text'])),
            $utils->sortTableBlocks($blocks)
        );
        $rows = array_chunk($cells, 2);
        $html = $wordpressTable($rows);

        $t->same(
            "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table><tbody>"
            . '<tr><td>Block</td><td>Status Review</td></tr>'
            . '<tr><td>Media 24</td><td>Draft</td></tr>'
            . "</tbody></table></figure>\n<!-- /wp:table -->\n",
            $html
        );
    },
];
