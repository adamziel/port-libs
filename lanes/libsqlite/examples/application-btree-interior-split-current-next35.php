<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeInteriorSplitCurrentNextPlan;
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
    8 => [SQLitePointerMapEntry::BTREE_PAGE, 4],
    9 => [SQLitePointerMapEntry::BTREE_PAGE, 4],
    10 => [SQLitePointerMapEntry::BTREE_PAGE, 4],
    11 => [SQLitePointerMapEntry::BTREE_PAGE, 5],
    12 => [SQLitePointerMapEntry::FREE_PAGE, 0],
] as $pageNumber => [$type, $parentPageNumber]) {
    $pointerMapPage = substr_replace($pointerMapPage, chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
}

$parent = SQLiteTableInteriorPage::assemble([
    SQLiteTableInteriorCell::encode(4, 900),
], 5, $pageSize);
$current = SQLiteTableInteriorPage::assemble([
    SQLiteTableInteriorCell::encode(6, 110),
    SQLiteTableInteriorCell::encode(7, 120),
    SQLiteTableInteriorCell::encode(8, 130),
    SQLiteTableInteriorCell::encode(9, 140),
], 10, $pageSize);
$right = SQLiteTableInteriorPage::assemble([
    SQLiteTableInteriorCell::encode(11, 1000),
], 11, $pageSize);

$database = SQLiteDatabase::fromBytes(
    $firstPage
    . $pointerMapPage
    . $parent
    . $current
    . $right
    . str_repeat("\0", $pageSize * 7),
);
$plan = SQLiteBTreeInteriorSplitCurrentNextPlan::tableCurrentIntoNext($database, 3, 4, 12);

$pages = [];
for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
    $pages[$pageNumber] = $plan->pageImages[$pageNumber] ?? $database->page($pageNumber);
}
$postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));

$keys = static function (SQLiteDatabase $db, int $pageNumber): array {
    $header = SQLiteBTreePageHeader::parsePage($db->page($pageNumber), $db->header->pageSize);

    return array_map(static fn (SQLiteTableInteriorCell $cell): int => $cell->key, SQLiteTableInteriorCell::parsePageCells($db->page($pageNumber), $header));
};

echo json_encode([
    'applicationUse' => 'Split a copied wp_options table-interior child into current/next pages, insert the parent divider, and rewrite auto-vacuum pointer-map ownership without ext/sqlite.',
    'plan' => $plan->toArray(),
    'parentKeys' => $keys($postDatabase, 3),
    'currentKeys' => $keys($postDatabase, 4),
    'nextKeys' => $keys($postDatabase, 12),
    'nextPointerMap' => $postDatabase->pointerMapEntryForPage(12)->toArray(),
    'movedChildPointerMapParents' => [
        9 => $postDatabase->pointerMapEntryForPage(9)->parentPageNumber,
        10 => $postDatabase->pointerMapEntryForPage(10)->parentPageNumber,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
