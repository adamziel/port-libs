<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreePointerMapFreelistOverflowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$firstPage = str_repeat("\0", 512);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', 512), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 9), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 8), 32, 4);
$firstPage = substr_replace($firstPage, pack('N', 2), 36, 4);
$firstPage = substr_replace($firstPage, pack('N', 4), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$pages = array_fill(1, 9, str_repeat("\0", 512));
$pages[1] = $firstPage;
$pages[2] = str_repeat("\0", 512);
$pages[3] = SQLiteTableLeafPage::assemble([]);
$pages[6] = pack('N', 7) . str_repeat('O', 508);
$pages[7] = pack('N', 0) . str_repeat('P', 508);
$pages[8] = SQLiteFreelistTrunkPage::assemble(null, [9], 512);

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2) {
        return;
    }

    $pages[2] = substr_replace(
        $pages[2],
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - 3),
        5,
    );
};

foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    6 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
    7 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 6],
    8 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    9 => [SQLitePointerMapEntry::FREE_PAGE, 0],
] as $pageNumber => [$type, $parent]) {
    $putPointerMapEntry($pages, $pageNumber, $type, $parent);
}

$plan = SQLiteBTreePointerMapFreelistOverflowCurrentSourceNextPlan::next129FromOverflowChains(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    [[
        'source' => 'wp-option-delete-stale-cache-overflow-chain',
        'first_page' => 6,
        'overflow_payload_bytes' => 700,
        'rowids' => [12901],
    ]],
    1300,
    3,
    str_repeat('N', 1300),
);

echo json_encode([
    'action' => $plan->toArray()['action'],
    'released_overflow_pages' => $plan->releasedOverflowPages(),
    'allocated_overflow_pages' => $plan->allocatedOverflowPages(),
    'page_origins' => array_column($plan->rows, 'page_origin'),
    'next_pointer_map_types' => array_column($plan->rows, 'next_pointer_map_type'),
    'next_pointer_map_parents' => array_column($plan->rows, 'next_pointer_map_parent'),
    'final_freelist_page_numbers' => $plan->databaseAfterAllocation->freelistPageNumbers(),
], JSON_PRETTY_PRINT) . PHP_EOL;
