<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteBTreeRootCollapsePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableInteriorPage;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;
use PortLibs\LibSqlite\SQLiteTableRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$pageCount = 106;
$pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
$pages[1] = str_repeat("\0", $pageSize);
$pages[1] = substr_replace($pages[1], "SQLite format 3\0", 0, 16);
$pages[1] = substr_replace($pages[1], pack('n', $pageSize), 16, 2);
$pages[1][18] = "\x01";
$pages[1][19] = "\x01";
$pages[1][20] = "\x00";
$pages[1][21] = "\x40";
$pages[1][22] = "\x20";
$pages[1][23] = "\x20";
$pages[1] = substr_replace($pages[1], pack('N', $pageCount), 28, 4);
$pages[1] = substr_replace($pages[1], pack('N', 4), 52, 4);
$pages[1] = substr_replace($pages[1], pack('N', 1), 56, 4);

$pages[3] = SQLiteTableInteriorPage::assemble([], 4, $pageSize);
$pages[4] = SQLiteTableInteriorPage::assemble([], 106, $pageSize);
$pages[106] = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
    SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'home', 'https://example.test/blog', 'yes'])),
], $pageSize);

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber) use ($pageSize): void {
    $stride = intdiv($pageSize, 5) + 1;
    $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
    if ($pointerMapPage === $pageNumber) {
        return;
    }

    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage],
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$putPointerMapEntry($pages, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
$putPointerMapEntry($pages, 4, SQLitePointerMapEntry::BTREE_PAGE, 3);
$putPointerMapEntry($pages, 106, SQLitePointerMapEntry::BTREE_PAGE, 4);

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$plan = SQLiteBTreeRootCollapsePlan::collapseOnlyChild($database, 3, true);
foreach ($plan->pageImages as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}

$postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));
$leafHeader = SQLiteBTreePageHeader::parsePage($postDatabase->page(106), $pageSize);
$options = array_map(
    static fn (SQLiteTableLeafCell $cell): array => SQLiteTableRow::fromTableLeafCell($cell)->values(),
    SQLiteTableLeafCell::parsePageCells($postDatabase->page(106), $leafHeader),
);

echo json_encode([
    'applicationUse' => 'Apply a copied wp_options auto-vacuum delete/rebalance root-collapse where the obsolete child is released on pointer-map page 2 while the adopted grandchild is reparented on pointer-map page 105.',
    'summary' => $plan->toArray(),
    'obsoleteChildPointerMap' => $postDatabase->pointerMapEntryForPage(4)->toArray(),
    'adoptedGrandchildPointerMap' => $postDatabase->pointerMapEntryForPage(106)->toArray(),
    'freelistPages' => $postDatabase->freelistPageNumbers(),
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
