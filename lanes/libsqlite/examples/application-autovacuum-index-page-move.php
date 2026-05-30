<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreePageMovePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexInteriorPage;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;

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
$firstPage = substr_replace($firstPage, pack('N', 4), 32, 4);
$firstPage = substr_replace($firstPage, pack('N', 2), 36, 4);
$firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$pointerMapPage = str_repeat("\0", $pageSize);
foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    4 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    5 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    6 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 9],
    7 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
    8 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
    9 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
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
$largeOptionName = str_repeat('z-autoload-fragment-', 24);
$largePayload = SQLiteRecord::encode(['yes', $largeOptionName, 203]);
$overflowCell = SQLiteIndexCell::encodeWithOverflowPages($largePayload, 6, $pageSize);
$parentPage = SQLiteIndexInteriorPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_m', 150]), $pageSize, null, 7),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_t', 180]), $pageSize, null, 8),
], 9, $pageSize);
$leftLeafPage = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['no', 'cron_lock', 10])),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_a', 11])),
], $pageSize);
$middleLeafPage = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_n', 150])),
], $pageSize);
$sourceLeafPage = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_z', 201])),
    $overflowCell['cell'],
], $pageSize);

$database = SQLiteDatabase::fromBytes(
    $firstPage
    . $pointerMapPage
    . $parentPage
    . $freelistTrunkPage
    . str_repeat("\0", $pageSize)
    . $overflowCell['overflowPages'][0]
    . $leftLeafPage
    . $middleLeafPage
    . $sourceLeafPage,
);
$plan = SQLiteBTreePageMovePlan::moveLastIndexLeafIntoFreelistSlot($database, 9, 3);
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
    'scenario' => 'application-autovacuum-index-page-move',
    'applicationUse' => 'Preview copied wp_options autoload index cleanup where the last index leaf page is moved into a lower freelist slot, the index parent pointer is rewritten, and overflow pointer-map ownership follows the moved page without ext/sqlite.',
    'plan' => $plan->toArray(),
    'movedIndexLeaf' => [
        'page' => 5,
        'cellCount' => $postDatabase->pageHeader(5)->cellCount,
        'records' => array_map(
            static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
            SQLiteIndexCell::parsePageCells(
                $postDatabase->page(5),
                $postDatabase->pageHeader(5),
                $postDatabase->usablePageSize(),
                static fn (int $firstOverflowPage, int $byteCount): string => substr($postDatabase->page($firstOverflowPage), 4, $byteCount),
            ),
        ),
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
        'overflowType' => $postDatabase->pointerMapEntryForPage(6)->typeName(),
        'overflowParent' => $postDatabase->pointerMapEntryForPage(6)->parentPageNumber,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
