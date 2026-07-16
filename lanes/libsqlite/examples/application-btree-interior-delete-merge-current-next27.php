<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeInteriorMergeApplicationPlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteTableInteriorCell;
use PortLibs\LibSqlite\SQLiteTableInteriorPage;

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
$firstPage = substr_replace($firstPage, pack('N', 12), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);

$pointerMapPage = str_repeat("\0", $pageSize);
foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    4 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
    5 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
    6 => [SQLitePointerMapEntry::BTREE_PAGE, 4],
    7 => [SQLitePointerMapEntry::BTREE_PAGE, 4],
    8 => [SQLitePointerMapEntry::BTREE_PAGE, 5],
    9 => [SQLitePointerMapEntry::BTREE_PAGE, 5],
    10 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
    11 => [SQLitePointerMapEntry::BTREE_PAGE, 10],
    12 => [SQLitePointerMapEntry::BTREE_PAGE, 10],
] as $pageNumber => [$type, $parentPageNumber]) {
    $pointerMapPage = substr_replace(
        $pointerMapPage,
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - 3),
        5,
    );
}

$rootPage = SQLiteTableInteriorPage::assemble([
    SQLiteTableInteriorCell::encode(4, 200),
    SQLiteTableInteriorCell::encode(5, 400),
], 10, $pageSize);
$currentPage = SQLiteTableInteriorPage::assemble([
    SQLiteTableInteriorCell::encode(6, 100),
], 7, $pageSize);
$nextPage = SQLiteTableInteriorPage::assemble([
    SQLiteTableInteriorCell::encode(8, 300),
], 9, $pageSize);
$tailPage = SQLiteTableInteriorPage::assemble([
    SQLiteTableInteriorCell::encode(11, 600),
], 12, $pageSize);

$database = SQLiteDatabase::fromBytes(
    $firstPage
    . $pointerMapPage
    . $rootPage
    . $currentPage
    . $nextPage
    . str_repeat("\0", $pageSize * 4)
    . $tailPage
    . str_repeat("\0", $pageSize * 2),
);

$application = SQLiteBTreeInteriorMergeApplicationPlan::tableCurrentAndNext($database, 3, 4, true);
$pages = [];
for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
    $pages[$pageNumber] = $application->pageImages[$pageNumber] ?? $database->page($pageNumber);
}
$postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));
$rootHeader = SQLiteBTreePageHeader::parsePage($postDatabase->page(3), $pageSize);
$mergedHeader = SQLiteBTreePageHeader::parsePage($postDatabase->page(4), $pageSize);
$rootCells = SQLiteTableInteriorCell::parsePageCells($postDatabase->page(3), $rootHeader);
$mergedCells = SQLiteTableInteriorCell::parsePageCells($postDatabase->page(4), $mergedHeader);

echo json_encode([
    'applicationUse' => 'Preview copied wp_options delete rebalancing where the current underfilled interior child merges with its next sibling, the parent divider is removed, the next page enters the freelist, and auto-vacuum pointer-map children move to the merged current page without ext/sqlite.',
    'application' => $application->toArray(),
    'rootAfter' => [
        'page' => 3,
        'cellCount' => $rootHeader->cellCount,
        'keys' => array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->key, $rootCells),
        'children' => [
            ...array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->leftChildPage, $rootCells),
            $rootHeader->rightMostPointer,
        ],
    ],
    'mergedCurrentAfter' => [
        'page' => 4,
        'cellCount' => $mergedHeader->cellCount,
        'keys' => array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->key, $mergedCells),
        'children' => [
            ...array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->leftChildPage, $mergedCells),
            $mergedHeader->rightMostPointer,
        ],
    ],
    'freelist' => [
        'firstTrunkPage' => $postDatabase->header->firstFreelistTrunkPage,
        'pageCount' => $postDatabase->header->freelistPageCount,
        'pages' => $postDatabase->freelistPageNumbers(),
    ],
    'pointerMap' => [
        'obsoleteNext' => $postDatabase->pointerMapEntryForPage(5)->toArray(),
        'movedChildren' => [
            8 => $postDatabase->pointerMapEntryForPage(8)->toArray(),
            9 => $postDatabase->pointerMapEntryForPage(9)->toArray(),
        ],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
