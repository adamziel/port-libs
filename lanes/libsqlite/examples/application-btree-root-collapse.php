<?php

declare(strict_types=1);

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
$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 4), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);

$pointerMapPage = str_repeat("\0", $pageSize);
foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    4 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
] as $pageNumber => [$type, $parentPageNumber]) {
    $pointerMapPage = substr_replace(
        $pointerMapPage,
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - 3),
        5,
    );
}

$rootPage = SQLiteTableInteriorPage::assemble([], 4, $pageSize);
$childPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'home', 'https://example.test', 'yes'])),
    SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, 'siteurl', 'https://example.test/wp', 'yes'])),
], $pageSize);

$database = SQLiteDatabase::fromBytes($firstPage . $pointerMapPage . $rootPage . $childPage);
$plan = SQLiteBTreeRootCollapsePlan::collapseOnlyChild($database, 3, true);

$pages = [];
for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
    $pages[$pageNumber] = $database->page($pageNumber);
}
foreach ($plan->pageImages as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}

$postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));
$rootHeader = $postDatabase->pageHeader(3);
$options = array_map(
    static fn (SQLiteTableLeafCell $cell): array => SQLiteTableRow::fromTableLeafCell($cell)->values(),
    SQLiteTableLeafCell::parsePageCells($postDatabase->page(3), $rootHeader),
);

echo json_encode([
    'applicationUse' => 'Apply a wp_options delete/rebalance root-collapse where an empty table-interior root copies its only child leaf into the root page, releases the obsolete child page to the freelist, and rewrites auto-vacuum pointer-map state without requiring ext/sqlite.',
    'plan' => $plan->toArray(),
    'rootPageType' => $rootHeader->pageType,
    'rootCellCount' => $rootHeader->cellCount,
    'freelistPages' => $postDatabase->freelistPageNumbers(),
    'obsoleteChildPointerMapType' => $postDatabase->pointerMapEntryForPage(4)->typeName(),
    'options' => $options,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
