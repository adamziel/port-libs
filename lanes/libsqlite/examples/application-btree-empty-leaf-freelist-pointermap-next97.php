<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteDatabase.php';
require __DIR__ . '/../src/SQLiteHeader.php';
require __DIR__ . '/../src/SQLiteVarint.php';
require __DIR__ . '/../src/SQLiteBTreePageHeader.php';
require __DIR__ . '/../src/SQLiteTableLeafPage.php';
require __DIR__ . '/../src/SQLiteIndexLeafPage.php';
require __DIR__ . '/../src/SQLitePointerMapEntry.php';
require __DIR__ . '/../src/SQLiteFreelistTraversalPlan.php';
require __DIR__ . '/../src/SQLiteFreelistTrunkPage.php';
require __DIR__ . '/../src/SQLiteFreelistFreePlan.php';
require __DIR__ . '/../src/SQLiteBTreeEmptyLeafBatchFreePlan.php';
require __DIR__ . '/../src/SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
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
$firstPage = substr_replace($firstPage, pack('N', 12), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 4), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$pages = array_fill(1, 12, str_repeat("\0", 512));
$pages[1] = $firstPage;
$pages[2] = str_repeat("\0", 512);
$pages[3] = SQLiteTableLeafPage::assemble([]);
$pages[4] = SQLiteIndexLeafPage::assemble([]);
foreach ([6 => 't', 7 => 'u', 8 => 'i', 9 => 'j', 10 => 'k'] as $pageNumber => $byte) {
    $pages[$pageNumber] = str_repeat($byte, 512);
}

$putPointerMap = static function (int $pageNumber, int $type, int $parentPageNumber) use (&$pages): void {
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
    3 => [SQLitePointerMapEntry::BTREE_PAGE, 5],
    4 => [SQLitePointerMapEntry::BTREE_PAGE, 5],
    5 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    6 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
    7 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 6],
    8 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4],
    9 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 8],
    10 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 9],
] as $pageNumber => [$type, $parentPage]) {
    $putPointerMap($pageNumber, $type, $parentPage);
}

$plan = SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan::fromDeleteResults(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    [
        [
            'leaf_page' => 3,
            'leaf_page_type' => 'table-leaf',
            'delete_result' => [
                'page' => $pages[3],
                'rowids' => [97, 98],
                'obsolete_overflow_page_numbers' => [6, 7],
            ],
        ],
        [
            'leaf_page' => 4,
            'leaf_page_type' => 'index-leaf',
            'delete_result' => [
                'page' => $pages[4],
                'record_values' => [
                    ['_transient_timeout_next97', 97],
                    ['_transient_feed_next97', 98],
                ],
                'obsolete_overflow_page_numbers' => [8, 9, 10],
            ],
        ],
    ],
    true,
);

echo json_encode([
    'scenario' => 'application-btree-empty-leaf-freelist-pointermap-next97',
    'applicationUse' => 'Release empty copied wp_options table and option_name index leaves after transient cleanup, chain their obsolete overflow pages through the freelist, and rewrite auto-vacuum pointer-map entries before the next insert.',
    'freedPages' => $plan->batchPlan->freedPageNumbers,
    'currentFreelistPages' => $plan->currentDatabase->freelistPageNumbers(),
    'allocationOrder' => $plan->currentDatabase->freelistAllocationOrder(),
    'pointerMapRows' => $plan->emptyLeafFreelistPointerMapRows(),
    'dependencyClosure' => 'no new support component needed; this composes existing native B-tree page assembly, freelist, and pointer-map primitives',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
