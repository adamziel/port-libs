<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowFreeblockCurrentNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$emptyPage = str_repeat("\0", $pageSize);
$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 11), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 4), 32, 4);
$firstPage = substr_replace($firstPage, pack('N', 2), 36, 4);
$firstPage = substr_replace($firstPage, pack('N', 4), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$pointerMapPage = str_repeat("\0", $pageSize);
foreach ([
    3 => [SQLitePointerMapEntry::BTREE_PAGE, 7],
    4 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    5 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
    6 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 5],
    7 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    10 => [SQLitePointerMapEntry::FREE_PAGE, 0],
] as $pageNumber => [$type, $parentPageNumber]) {
    $pointerMapPage = substr_replace($pointerMapPage, chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
}

$database = SQLiteDatabase::fromBytes(
    $firstPage
    . $pointerMapPage
    . $emptyPage
    . SQLiteFreelistTrunkPage::assemble(null, [10], $pageSize)
    . $emptyPage
    . $emptyPage
    . $emptyPage
    . $emptyPage
    . $emptyPage
    . $emptyPage
    . $emptyPage,
);

$plan = SQLiteBTreeOverflowFreeblockCurrentNextPlan::replaceFromDeleteResults(
    $database,
    [['source' => 'copied-wp-options-transient-delete', 'obsolete_overflow_page_numbers' => [5, 6]]],
    str_repeat('replacement-overflow:', 80),
    3,
    true,
    true,
);

echo json_encode([
    'applicationUse' => 'After deleting an overflow-backed copied wp_options transient, immediately reuse the current/next freelist pages for the replacement overflow chain and rewrite auto-vacuum pointer-map ownership.',
    'overflowFreeblockCurrentNext' => $plan->toArray(),
    'freelistAfterReplacement' => $plan->database->freelistPageNumbers(),
    'pointerMapTypes' => [
        10 => $plan->database->pointerMapEntryForPage(10)->typeName(),
        6 => $plan->database->pointerMapEntryForPage(6)->typeName(),
        5 => $plan->database->pointerMapEntryForPage(5)->typeName(),
        4 => $plan->database->pointerMapEntryForPage(4)->typeName(),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
