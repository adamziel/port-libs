<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeFreelistPointerMapOverflowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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

    $pages[2] = substr_replace(
        $pages[2],
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - 3),
        5,
    );
};

$overflowPage = static fn (int $nextPage, string $byte): string => pack('N', $nextPage) . str_repeat($byte, 508);

$pages = array_fill(1, 12, str_repeat("\0", 512));
$pages[1] = $makeFirstPage(12, 10, 2);
$pages[2] = str_repeat("\0", 512);
$pages[3] = SQLiteTableLeafPage::assemble([]);
$pages[4] = SQLiteTableLeafPage::assemble([]);
$pages[6] = $overflowPage(7, 'A');
$pages[7] = $overflowPage(0, 'B');
$pages[8] = $overflowPage(9, 'C');
$pages[9] = $overflowPage(0, 'D');
$pages[10] = SQLiteFreelistTrunkPage::assemble(null, [12], 512);
$pages[12] = str_repeat("\0", 512);

foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    4 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
    6 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
    7 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 6],
    8 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4],
    9 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 8],
    10 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    12 => [SQLitePointerMapEntry::FREE_PAGE, 0],
] as $pageNumber => [$type, $parent]) {
    $putPointerMapEntry($pages, $pageNumber, $type, $parent);
}

$plan = SQLiteBTreeFreelistPointerMapOverflowCurrentSourceNextPlan::fromOverflowChains(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    [
        [
            'source' => 'wp-options-transient-table-overflow-delete-next136',
            'first_page' => 6,
            'overflow_payload_bytes' => 700,
            'rowids' => [13601],
        ],
        [
            'source' => 'wp-options-autoload-index-overflow-delete-next136',
            'first_page' => 8,
            'overflow_payload_bytes' => 700,
            'record_values' => [['autoload', 'option_name']],
        ],
    ],
    str_repeat('R', 3000),
    4,
);

$summary = $plan->toArray();

echo json_encode(
    [
        'action' => $summary['action'],
        'allocated_overflow_pages' => $summary['allocated_overflow_pages'],
        'final_freelist_page_count' => $summary['final_freelist_page_count'],
        'trunk_overflow_rows' => $summary['trunk_overflow_rows'],
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
) . PHP_EOL;
