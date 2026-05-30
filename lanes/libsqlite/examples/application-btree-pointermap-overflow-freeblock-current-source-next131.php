<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreePointerMapOverflowFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

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

$leafPage = str_repeat("\xcc", 512);
$leafPage[0] = "\x0d";
$leafPage = substr_replace($leafPage, pack('n', 400), 1, 2);
$leafPage = substr_replace($leafPage, pack('n', 1), 3, 2);
$leafPage = substr_replace($leafPage, pack('n', 384), 5, 2);
$leafPage[7] = chr(6);
$leafPage = substr_replace($leafPage, pack('n', 500), 8, 2);
$leafPage = substr_replace($leafPage, str_repeat('W', 8), 500, 8);
$leafPage = substr_replace($leafPage, pack('n', 413) . pack('n', 12), 400, 4);
$leafPage = substr_replace($leafPage, pack('n', 428) . pack('n', 12), 413, 4);
$leafPage = substr_replace($leafPage, pack('n', 0) . pack('n', 16), 428, 4);

$pages = array_fill(1, 9, str_repeat("\0", 512));
$pages[1] = $firstPage;
$pages[2] = str_repeat("\0", 512);
$pages[3] = $leafPage;
$pages[5] = pack('N', 6) . str_repeat('O', 508);
$pages[6] = pack('N', 0) . str_repeat('P', 508);
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
    5 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
    6 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 5],
    8 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    9 => [SQLitePointerMapEntry::FREE_PAGE, 0],
] as $pageNumber => [$type, $parentPageNumber]) {
    $putPointerMapEntry($pages, $pageNumber, $type, $parentPageNumber);
}

$plan = SQLiteBTreePointerMapOverflowFreeblockCurrentSourceNextPlan::pointerMapOverflowFreeblockFromDeleteResults(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    3,
    [[
        'source' => 'wp_options-current-source-next131',
        'obsolete_overflow_page_numbers' => [5, 6],
        'rowids' => [13101],
    ]],
    3,
    str_repeat('R', 1300),
);

echo json_encode([
    'action' => $plan->toArray()['action'],
    'coalesced_fragment_bytes' => $plan->coalescePlan->coalescedFragmentBytes,
    'released_overflow_pages' => $plan->releasedOverflowPages(),
    'allocated_overflow_pages' => $plan->allocatedOverflowPages(),
    'page_origins' => array_column($plan->rows, 'page_origin'),
    'next_pointer_map_types' => array_column($plan->rows, 'next_pointer_map_type'),
    'next_pointer_map_parents' => array_column($plan->rows, 'next_pointer_map_parent'),
    'final_freelist_page_numbers' => $plan->databaseAfterAllocation->freelistPageNumbers(),
], JSON_PRETTY_PRINT) . PHP_EOL;
