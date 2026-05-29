<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteFreelistTrunkPage.php';
require_once __DIR__ . '/../src/SQLiteFreelistTraversalPlan.php';
require_once __DIR__ . '/../src/SQLiteFreelistAllocationPlan.php';
require_once __DIR__ . '/../src/SQLiteOverflowPage.php';
require_once __DIR__ . '/../src/SQLitePointerMapEntry.php';
require_once __DIR__ . '/../src/SQLiteBTreePageHeader.php';
require_once __DIR__ . '/../src/SQLiteBTreeOverflowPointerMapPageMoveCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteBTreeOverflowPointerMapPageMoveCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$pageSize = 512;
$pageCount = 12;
$pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', $pageCount), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 4), 32, 4);
$firstPage = substr_replace($firstPage, pack('N', 2), 36, 4);
$firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);
$pages[1] = $firstPage;
$pages[2] = str_repeat("\0", $pageSize);
$pages[4] = SQLiteFreelistTrunkPage::assemble(null, [7], $pageSize);

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};

$payload = str_repeat('wp_options autoload cache overflow moved by next111 ', 24);
foreach (SQLiteOverflowPage::encodeChainAtPages($payload, [6, 10, 12], $pageSize) as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}
foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    4 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    7 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    6 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
    10 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 6],
    12 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 10],
] as $pageNumber => [$type, $parentPageNumber]) {
    $putPointerMapEntry($pages, $pageNumber, $type, $parentPageNumber);
}

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$plan = SQLiteBTreeOverflowPointerMapPageMoveCurrentSourceNextPlan::moveLastOverflowPageIntoFreelistSlot($database, 12, 10);
$summary = [
    'scenario' => 'wordpress-btree-overflow-pointermap-page-move-current-source-next111',
    'wordpressUse' => 'During copied wp_options autoload cache cleanup, autovacuum can move the final overflow page into a lower freelist slot; the previous overflow page next pointer and pointer-map parent must both retarget the moved page.',
    'action' => $plan->toArray()['action'],
    'sourcePage' => $plan->sourcePageNumber,
    'targetPage' => $plan->targetPageNumber,
    'previousOverflowPage' => $plan->previousOverflowPageNumber,
    'previousNextAfterMove' => unpack('N', substr($plan->databaseAfter->page(10), 0, 4))[1],
    'targetPointerMapType' => $plan->databaseAfter->pointerMapEntryForPage(7)->typeName(),
    'targetPointerMapParent' => $plan->databaseAfter->pointerMapEntryForPage(7)->parentPageNumber,
    'databasePageCount' => $plan->databaseAfter->header->databaseSizePages,
    'updatedPointerMapPages' => $plan->updatedPointerMapPageNumbers,
    'dependencyClosure' => 'no new support component needed; this composes existing SQLite database image, freelist allocation, overflow page, and pointer-map primitives',
];

if (
    $summary['previousNextAfterMove'] !== 7
    || $summary['targetPointerMapType'] !== 'overflow-page'
    || $summary['targetPointerMapParent'] !== 10
    || $summary['databasePageCount'] !== 11
) {
    fwrite(STDERR, "wordpress-btree-overflow-pointermap-page-move-current-source-next111 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
