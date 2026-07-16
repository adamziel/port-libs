<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreeDeleteOverflowMaterializationPlan;
use PortLibs\LibSqlite\SQLiteBTreeEmptyLeafBatchFreePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 1024;
$pageCount = 220;
$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\xff";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', $pageCount), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 4), 32, 4);
$firstPage = substr_replace($firstPage, pack('N', 185), 36, 4);
$firstPage = substr_replace($firstPage, pack('N', 7), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
$pages[1] = $firstPage;
$pages[2] = str_repeat("\0", $pageSize);
$pages[3] = SQLiteTableLeafPage::assemble([], $pageSize);
$pages[4] = SQLiteFreelistTrunkPage::assemble(null, range(20, 203), $pageSize, $pageSize - 255);
$pages[5] = SQLiteIndexLeafPage::assemble([], $pageSize);
foreach ([6, 9, 10] as $pageNumber) {
    $pages[$pageNumber] = str_repeat(chr(64 + $pageNumber), $pageSize);
}

$putPointerMapEntry = static function (int $pageNumber, int $type, int $parentPageNumber) use (&$pages): void {
    $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};

foreach (range(20, 203) as $pageNumber) {
    $pages[$pageNumber] = str_repeat(chr($pageNumber % 251), $pageSize);
    $putPointerMapEntry($pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0);
}
foreach ([
    3 => [SQLitePointerMapEntry::BTREE_PAGE, 7],
    4 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    5 => [SQLitePointerMapEntry::BTREE_PAGE, 8],
    6 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
    9 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 6],
    10 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 5],
    7 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    8 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
] as $pageNumber => [$type, $parentPageNumber]) {
    $putPointerMapEntry($pageNumber, $type, $parentPageNumber);
}

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$batch = SQLiteBTreeEmptyLeafBatchFreePlan::fromDeleteResults($database, [
    [
        'leaf_page' => 3,
        'leaf_page_type' => 'table-leaf',
        'delete_result' => [
            'page' => $pages[3],
            'rowids' => [301, 302],
            'obsolete_overflow_page_numbers' => [6, 9],
        ],
    ],
    [
        'leaf_page' => 5,
        'leaf_page_type' => 'index-leaf',
        'delete_result' => [
            'page' => $pages[5],
            'record_values' => [['_transient_timeout_btree75', 301]],
            'obsolete_overflow_page_numbers' => [10],
        ],
    ],
], true);
$materialization = SQLiteBTreeDeleteOverflowMaterializationPlan::fromEmptyLeafBatchPlan($database, $batch);
$summary = $materialization->toArray();

echo 'Application btree75 materialized action: ' . $summary['source_action'] . PHP_EOL;
echo 'Application btree75 next freelist trunk: ' . $summary['next_first_freelist_trunk_page'] . PHP_EOL;
echo 'Application btree75 next freelist count: ' . $summary['next_freelist_page_count'] . PHP_EOL;
echo 'Application btree75 pointer-map transitions: ' . implode(',', array_column($summary['pointer_map_transitions'], 'page_number')) . PHP_EOL;
echo 'Application btree75 updated pages: ' . implode(',', $summary['updated_page_numbers']) . PHP_EOL;
