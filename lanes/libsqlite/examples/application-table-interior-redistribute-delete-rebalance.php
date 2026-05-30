<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeInteriorRedistributionPlan;
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
$firstPage = substr_replace($firstPage, pack('N', 10), 28, 4);
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
    10 => [SQLitePointerMapEntry::BTREE_PAGE, 5],
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
], 5, $pageSize);
$leftParentPage = SQLiteTableInteriorPage::assemble([
    SQLiteTableInteriorCell::encode(6, 100),
], 7, $pageSize);
$rightParentPage = SQLiteTableInteriorPage::assemble([
    SQLiteTableInteriorCell::encode(8, 300),
    SQLiteTableInteriorCell::encode(9, 400),
], 10, $pageSize);

$database = SQLiteDatabase::fromBytes(
    $firstPage
    . $pointerMapPage
    . $rootPage
    . $leftParentPage
    . $rightParentPage
    . str_repeat("\0", $pageSize * 5),
);

$plan = SQLiteBTreeInteriorRedistributionPlan::tableInterior(
    $leftParentPage,
    $rightParentPage,
    4,
    5,
    3,
    200,
    $pageSize,
);
$postPointerMapPages = $database->planPointerMapUpdates($plan->pointerMapUpdates);
$postDatabase = SQLiteDatabase::fromBytes(
    $firstPage
    . $postPointerMapPages[2]
    . $rootPage
    . $plan->leftPage
    . $plan->rightPage
    . str_repeat("\0", $pageSize * 5),
);

echo json_encode([
    'applicationUse' => 'Preview a copied wp_options table b-tree delete rebalance where an underfilled interior parent borrows a child pointer from its right sibling and rewrites auto-vacuum pointer-map parent links without ext/sqlite.',
    'plan' => $plan->toArray(),
    'leftParent' => [
        'page' => 4,
        'cellCount' => $postDatabase->pageHeader(4)->cellCount,
        'rightMostPointer' => $postDatabase->pageHeader(4)->rightMostPointer,
    ],
    'rightParent' => [
        'page' => 5,
        'cellCount' => $postDatabase->pageHeader(5)->cellCount,
        'rightMostPointer' => $postDatabase->pageHeader(5)->rightMostPointer,
    ],
    'pointerMap' => [
        'movedChild' => [
            'page' => 8,
            'type' => $postDatabase->pointerMapEntryForPage(8)->typeName(),
            'parentPage' => $postDatabase->pointerMapEntryForPage(8)->parentPageNumber,
        ],
        'rightSiblingChildren' => [
            9 => $postDatabase->pointerMapEntryForPage(9)->parentPageNumber,
            10 => $postDatabase->pointerMapEntryForPage(10)->parentPageNumber,
        ],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
