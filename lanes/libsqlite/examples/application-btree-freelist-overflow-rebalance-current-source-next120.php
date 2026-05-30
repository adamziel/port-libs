<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$firstPage = static function (): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', 12), 28, 4);
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2) {
        return;
    }

    $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};

$tableDatabase = static function () use ($firstPage, $putPointerMapEntry): SQLiteDatabase {
    $pages = array_fill(1, 12, str_repeat("\0", 512));
    $pages[1] = $firstPage();
    $pages[2] = str_repeat("\0", 512);
    $firstPayload = SQLiteRecord::encode([null, '_transient_timeout_plugins', str_repeat('a', 1077)]);
    $secondPayload = SQLiteRecord::encode([null, '_site_transient_update_plugins', str_repeat('b', 2095)]);
    $first = SQLiteTableLeafCell::encodeWithOverflowPages(21, $firstPayload, 6, 512);
    $second = SQLiteTableLeafCell::encodeWithOverflowPages(22, $secondPayload, 8, 512);
    $pages[3] = SQLiteTableLeafPage::assemble([$first['cell'], $second['cell']]);
    foreach ($first['overflowPages'] as $offset => $overflowPage) {
        $pages[6 + $offset] = $overflowPage;
    }
    foreach ($second['overflowPages'] as $offset => $overflowPage) {
        $pages[8 + $offset] = $overflowPage;
    }
    $putPointerMapEntry($pages, 3, SQLitePointerMapEntry::BTREE_PAGE, 4);
    $putPointerMapEntry($pages, 4, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $putPointerMapEntry($pages, 5, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $putPointerMapEntry($pages, 6, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $putPointerMapEntry($pages, 7, SQLitePointerMapEntry::OVERFLOW_PAGE, 6);
    $putPointerMapEntry($pages, 8, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $putPointerMapEntry($pages, 9, SQLitePointerMapEntry::OVERFLOW_PAGE, 8);
    $putPointerMapEntry($pages, 10, SQLitePointerMapEntry::OVERFLOW_PAGE, 9);
    $putPointerMapEntry($pages, 11, SQLitePointerMapEntry::OVERFLOW_PAGE, 10);

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$indexRecords = [
    ['autoload', '_transient_timeout_plugins', str_repeat('a', 1077), 21],
    ['autoload', '_site_transient_update_plugins', str_repeat('b', 2095), 22],
];

$indexDatabase = static function () use ($firstPage, $putPointerMapEntry, $indexRecords): SQLiteDatabase {
    $pages = array_fill(1, 12, str_repeat("\0", 512));
    $pages[1] = $firstPage();
    $pages[2] = str_repeat("\0", 512);
    $first = SQLiteIndexCell::encodeWithOverflowPages(SQLiteRecord::encode($indexRecords[0]), 6, 512);
    $second = SQLiteIndexCell::encodeWithOverflowPages(SQLiteRecord::encode($indexRecords[1]), 8, 512);
    $pages[3] = SQLiteIndexLeafPage::assemble([$first['cell'], $second['cell']]);
    foreach ($first['overflowPages'] as $offset => $overflowPage) {
        $pages[6 + $offset] = $overflowPage;
    }
    foreach ($second['overflowPages'] as $offset => $overflowPage) {
        $pages[8 + $offset] = $overflowPage;
    }
    $putPointerMapEntry($pages, 3, SQLitePointerMapEntry::BTREE_PAGE, 5);
    $putPointerMapEntry($pages, 4, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $putPointerMapEntry($pages, 5, SQLitePointerMapEntry::ROOT_PAGE, 0);
    $putPointerMapEntry($pages, 6, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $putPointerMapEntry($pages, 7, SQLitePointerMapEntry::OVERFLOW_PAGE, 6);
    $putPointerMapEntry($pages, 8, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
    $putPointerMapEntry($pages, 9, SQLitePointerMapEntry::OVERFLOW_PAGE, 8);
    $putPointerMapEntry($pages, 10, SQLitePointerMapEntry::OVERFLOW_PAGE, 9);
    $putPointerMapEntry($pages, 11, SQLitePointerMapEntry::OVERFLOW_PAGE, 10);
    $putPointerMapEntry($pages, 12, SQLitePointerMapEntry::OVERFLOW_PAGE, 11);

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$tablePlan = SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan::tableLeaf($tableDatabase(), 3, [21, 22], true);
$indexPlan = SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan::indexLeaf($indexDatabase(), 3, $indexRecords, true);

$summary = [
    'scenario' => 'application-btree-freelist-overflow-rebalance-current-source-next120',
    'applicationUse' => 'Delete oversized transient option rows and matching index records from a copied wp_options database image, derive overflow chains from current page bytes, and return table/index leaf plus overflow pages to the freelist with auto-vacuum pointer-map rewrites.',
    'table' => [
        'released_pages' => $tablePlan->releasedPageNumbers(),
        'freelist' => $tablePlan->databaseAfter()->freelistPageNumbers(),
        'derived_overflow_page_numbers' => $tablePlan->toArray()['derived_overflow_page_numbers'],
        'leaf_pointer_map' => $tablePlan->databaseAfter()->pointerMapEntryForPage(3)->typeName(),
        'final_freelist_count' => $tablePlan->finalFreelistPageCount(),
    ],
    'index' => [
        'released_pages' => $indexPlan->releasedPageNumbers(),
        'freelist' => $indexPlan->databaseAfter()->freelistPageNumbers(),
        'derived_overflow_page_numbers' => $indexPlan->toArray()['derived_overflow_page_numbers'],
        'leaf_pointer_map' => $indexPlan->databaseAfter()->pointerMapEntryForPage(3)->typeName(),
        'final_freelist_count' => $indexPlan->finalFreelistPageCount(),
    ],
];

if (in_array('--self-test', $argv, true)) {
    if (
        $summary['table']['final_freelist_count'] !== 7
        || $summary['index']['final_freelist_count'] !== 8
        || $summary['table']['leaf_pointer_map'] !== 'free-page'
        || $summary['index']['leaf_pointer_map'] !== 'free-page'
    ) {
        fwrite(STDERR, "application-btree-freelist-overflow-rebalance-current-source-next120 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-btree-freelist-overflow-rebalance-current-source-next120 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
