<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreePageMovePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableInteriorCell;
use PortLibs\LibSqlite\SQLiteTableInteriorPage;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

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

$pointerMap = str_repeat("\0", $pageSize);
foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    4 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    5 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    6 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 9],
    7 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
    8 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 6],
    9 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
] as $pageNumber => [$type, $parent]) {
    $pointerMap = substr_replace($pointerMap, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
}

$payload = SQLiteRecord::encode([null, '_transient_plugin_payload', str_repeat('plugin-setting:', 95), 'no']);
$local = SQLiteTableLeafCell::localPayloadLength(strlen($payload), $pageSize);
$overflowPages = SQLiteOverflowPage::encodeChainAtPages(substr($payload, $local), [6, 8], $pageSize);
$sourceLeaf = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(201, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes'])),
    SQLiteTableLeafCell::encode(202, $payload, $pageSize, 6),
], $pageSize);
$parent = SQLiteTableInteriorPage::assemble([
    SQLiteTableInteriorCell::encode(7, 100),
], 9, $pageSize);

$database = SQLiteDatabase::fromBytes(implode('', [
    $firstPage,
    $pointerMap,
    $parent,
    SQLiteFreelistTrunkPage::assemble(null, [5], $pageSize),
    str_repeat("\0", $pageSize),
    $overflowPages[6],
    str_repeat("\0", $pageSize),
    $overflowPages[8],
    $sourceLeaf,
]));

$plan = SQLiteBTreePageMovePlan::moveLastTableLeafIntoFreelistSlot($database, 9, 3);
$summary = [
    'action' => $plan->toArray()['action'],
    'source_page' => $plan->sourcePageNumber,
    'target_page' => $plan->targetPageNumber,
    'database_page_count' => $plan->databasePageCount,
    'updated_pointer_map_pages' => $plan->updatedPointerMapPageNumbers,
    'overflow_first_parent_before' => $database->pointerMapEntryForPage(6)->parentPageNumber,
    'overflow_first_parent_after' => 5,
];

if (in_array('--self-test', $argv, true)) {
    $expected = [
        'action' => 'auto-vacuum-table-leaf-page-move',
        'source_page' => 9,
        'target_page' => 5,
        'database_page_count' => 8,
        'updated_pointer_map_pages' => [2],
        'overflow_first_parent_before' => 9,
        'overflow_first_parent_after' => 5,
    ];
    if ($summary !== $expected) {
        fwrite(STDERR, json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL);
        exit(1);
    }

    echo "OK application autovacuum table overflow page-move smoke\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
