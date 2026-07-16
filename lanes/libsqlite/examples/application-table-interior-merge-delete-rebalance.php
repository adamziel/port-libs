<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeInteriorMergeApplicationPlan;
use PortLibs\LibSqlite\SQLiteBTreeInteriorMergePlan;
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
$firstPage = substr_replace($firstPage, pack('N', 9), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 5), 56, 4);

$pointerMapPage = str_repeat("\0", $pageSize);
foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    4 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
    5 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
    6 => [SQLitePointerMapEntry::BTREE_PAGE, 4],
    7 => [SQLitePointerMapEntry::BTREE_PAGE, 4],
    8 => [SQLitePointerMapEntry::BTREE_PAGE, 5],
    9 => [SQLitePointerMapEntry::BTREE_PAGE, 5],
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
], 9, $pageSize);

$database = SQLiteDatabase::fromBytes(
    $firstPage
    . $pointerMapPage
    . $rootPage
    . $leftParentPage
    . $rightParentPage
    . str_repeat("\0", $pageSize * 4),
);

$plan = SQLiteBTreeInteriorMergePlan::tableInterior(
    $leftParentPage,
    $rightParentPage,
    4,
    5,
    3,
    200,
    $pageSize,
);
$application = SQLiteBTreeInteriorMergeApplicationPlan::apply($database, $plan, true);
$pages = [];
for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
    $pages[$pageNumber] = $database->page($pageNumber);
}
foreach ($application->pageImages as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}
$postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));

echo json_encode([
    'applicationUse' => 'Preview a copied wp_options table b-tree delete rebalance where two underfilled interior siblings merge, the obsolete sibling becomes a freelist trunk, and auto-vacuum pointer-map children move to the merged parent without ext/sqlite.',
    'plan' => $plan->toArray(),
    'application' => $application->toArray(),
    'mergedParent' => [
        'page' => 4,
        'cellCount' => $postDatabase->pageHeader(4)->cellCount,
        'rightMostPointer' => $postDatabase->pageHeader(4)->rightMostPointer,
    ],
    'freelist' => [
        'firstTrunkPage' => $postDatabase->header->firstFreelistTrunkPage,
        'pageCount' => $postDatabase->header->freelistPageCount,
        'pages' => $postDatabase->freelistPageNumbers(),
    ],
    'pointerMap' => [
        'obsoleteSibling' => [
            'page' => 5,
            'type' => $postDatabase->pointerMapEntryForPage(5)->typeName(),
            'parentPage' => $postDatabase->pointerMapEntryForPage(5)->parentPageNumber,
        ],
        'mergedChildren' => [
            6 => $postDatabase->pointerMapEntryForPage(6)->parentPageNumber,
            7 => $postDatabase->pointerMapEntryForPage(7)->parentPageNumber,
            8 => $postDatabase->pointerMapEntryForPage(8)->parentPageNumber,
            9 => $postDatabase->pointerMapEntryForPage(9)->parentPageNumber,
        ],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
