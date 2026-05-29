<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreePointerMapFreelistOverflowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage = static function (int $pageCount, int $firstFreelistTrunkPage, int $freelistPageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $firstFreelistTrunkPage), 32, 4);
    $page = substr_replace($page, pack('N', $freelistPageCount), 36, 4);
    $page = substr_replace($page, pack('N', 3), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2) {
        return;
    }

    $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};

$overflowPage = static fn (int $nextPage, string $byte): string => pack('N', $nextPage) . str_repeat($byte, 508);

$database = static function (bool $current) use ($makeFirstPage, $putPointerMapEntry, $overflowPage): SQLiteDatabase {
    $pages = array_fill(1, 12, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage(12, 10, 2);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([]);
    $pages[4] = SQLiteTableLeafPage::assemble([]);
    $pages[6] = $overflowPage(7, $current ? 's' : 'P');
    $pages[7] = $overflowPage(0, $current ? 't' : 'Q');
    $pages[8] = $overflowPage(9, $current ? 'C' : 'u');
    $pages[9] = $overflowPage(0, $current ? 'D' : 'v');
    $pages[10] = SQLiteFreelistTrunkPage::assemble(null, [12], 512);

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        4 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
        6 => $current ? [SQLitePointerMapEntry::BTREE_PAGE, 3] : [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        7 => $current ? [SQLitePointerMapEntry::BTREE_PAGE, 3] : [SQLitePointerMapEntry::OVERFLOW_PAGE, 6],
        8 => $current ? [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4] : [SQLitePointerMapEntry::BTREE_PAGE, 3],
        9 => $current ? [SQLitePointerMapEntry::OVERFLOW_PAGE, 8] : [SQLitePointerMapEntry::BTREE_PAGE, 3],
        10 => [SQLitePointerMapEntry::FREE_PAGE, 0],
        12 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan = SQLiteBTreePointerMapFreelistOverflowCurrentSourceNextPlan::next141FromPreparedAndCurrentOverflowChains(
    $database(false),
    $database(true),
    [[
        'source' => 'prepared-wp-options-large-transient-row',
        'first_page' => 6,
        'overflow_payload_bytes' => 700,
        'rowids' => [14100],
    ]],
    [[
        'source' => 'current-wp-options-autoload-index-entry',
        'first_page' => 8,
        'overflow_payload_bytes' => 700,
        'record_values' => [['autoload', 'option_name']],
    ]],
    str_repeat('N', 1200),
    4,
);

$summary = [
    'prepared_pages_not_freed' => $plan->stalePreparedOverflowPages(),
    'current_pages_released' => $plan->releasedOverflowPages(),
    'allocated_pages' => $plan->allocatedOverflowPages(),
    'final_page_six_pointer_type' => $plan->databaseAfterAllocation->pointerMapEntryForPage(6)->typeName(),
    'final_page_twelve_pointer_parent' => $plan->databaseAfterAllocation->pointerMapEntryForPage(12)->parentPageNumber,
    'final_freelist_pages' => $plan->databaseAfterAllocation->freelistPageNumbers(),
];

if (($argv[1] ?? null) === '--self-test') {
    foreach ([
        'prepared_pages_not_freed' => [6, 7],
        'current_pages_released' => [8, 9],
        'allocated_pages' => [12, 9, 8],
        'final_page_six_pointer_type' => 'btree-page',
        'final_page_twelve_pointer_parent' => 4,
        'final_freelist_pages' => [10],
    ] as $key => $expected) {
        if ($summary[$key] !== $expected) {
            throw new RuntimeException("Unexpected {$key}: " . json_encode($summary[$key]));
        }
    }

    echo "wordpress-btree-pointermap-freelist-overflow-current-source-next141 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
