<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreePageMovePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableInteriorCell;
use PortLibs\LibSqlite\SQLiteTableInteriorPage;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

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
$firstPage = substr_replace($firstPage, pack('N', 8), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 4), 32, 4);
$firstPage = substr_replace($firstPage, pack('N', 2), 36, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$pointerMapPage = str_repeat("\0", $pageSize);
foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    4 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    5 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    6 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
    7 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
    8 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
] as $pageNumber => [$type, $parentPageNumber]) {
    $pointerMapPage = substr_replace(
        $pointerMapPage,
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - 3),
        5,
    );
}

$freelistTrunkPage = substr_replace(str_repeat("\0", $pageSize), pack('N', 1), 4, 4);
$freelistTrunkPage = substr_replace($freelistTrunkPage, pack('N', 5), 8, 4);
$parentPage = SQLiteTableInteriorPage::assemble([
    SQLiteTableInteriorCell::encode(6, 100),
    SQLiteTableInteriorCell::encode(7, 150),
], 8, $pageSize);
$leftLeafPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(10, SQLiteRecord::encode([null, 'autoload_a', 'a:1:{}', 'yes'])),
]);
$middleLeafPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(150, SQLiteRecord::encode([null, 'autoload_m', 'a:1:{}', 'yes'])),
]);
$sourceLeafPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(201, SQLiteRecord::encode([null, 'autoload_z', 'a:1:{}', 'yes'])),
    SQLiteTableLeafCell::encode(202, SQLiteRecord::encode([null, 'transient_z', 's:5:"value";', 'no'])),
]);

$database = SQLiteDatabase::fromBytes(
    $firstPage
    . $pointerMapPage
    . $parentPage
    . $freelistTrunkPage
    . str_repeat("\0", $pageSize)
    . $leftLeafPage
    . $middleLeafPage
    . $sourceLeafPage,
);
$plan = SQLiteBTreePageMovePlan::moveLastTableLeafIntoFreelistSlot($database, 8, 3);
$pages = [];
for ($pageNumber = 1; $pageNumber <= $plan->databasePageCount; $pageNumber++) {
    $pages[$pageNumber] = $database->page($pageNumber);
}
foreach ($plan->pageImages as $pageNumber => $page) {
    if ($pageNumber <= $plan->databasePageCount) {
        $pages[$pageNumber] = $page;
    }
}
$postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));

echo json_encode([
    'scenario' => 'application-autovacuum-page-move',
    'applicationUse' => 'Preview copied wp_options auto-vacuum cleanup where the last table leaf page is moved into a freed lower page, the parent pointer is rewritten, and the database image is truncated without ext/sqlite.',
    'plan' => $plan->toArray(),
    'movedLeaf' => [
        'page' => 5,
        'cellCount' => $postDatabase->pageHeader(5)->cellCount,
        'rowIds' => array_map(static fn ($row): int => $row->rowId, $postDatabase->tableRows(5)),
        'optionNames' => array_map(static fn ($row): mixed => $row->values()[1] ?? null, $postDatabase->tableRows(5)),
    ],
    'parent' => [
        'page' => 3,
        'rightMostPointer' => $postDatabase->pageHeader(3)->rightMostPointer,
    ],
    'freelist' => [
        'firstTrunkPage' => $postDatabase->header->firstFreelistTrunkPage,
        'pageCount' => $postDatabase->header->freelistPageCount,
        'pages' => $postDatabase->freelistPageNumbers(),
    ],
    'pointerMap' => [
        'movedPageType' => $postDatabase->pointerMapEntryForPage(5)->typeName(),
        'movedPageParent' => $postDatabase->pointerMapEntryForPage(5)->parentPageNumber,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
