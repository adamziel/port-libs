<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreeEmptyLeafBatchFreePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 1024;
$pageCount = 210;
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
$pages[4] = SQLiteFreelistTrunkPage::assemble(null, range(20, 203), $pageSize, $pageSize - 255);

$putPointerMapEntry = static function (int $pageNumber, int $type, int $parentPageNumber) use (&$pages): void {
    $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};

foreach (range(20, 203) as $pageNumber) {
    $pages[$pageNumber] = str_repeat(chr($pageNumber % 251), $pageSize);
    $putPointerMapEntry($pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0);
}

$tablePage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(41, SQLiteRecord::encode([null, '_transient_rebalance_batch', 'expired', 'no']), $pageSize),
], $pageSize);
$tableDelete = SQLiteTableLeafPage::deleteCellByRowIdWithOverflowRelease(
    $tablePage,
    41,
    static fn (): array => [],
    $pageSize,
    secureDelete: true,
);
$pages[3] = $tablePage;

$indexRecord = ['_transient_rebalance_batch', 41];
$indexPage = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode($indexRecord)),
], $pageSize);
$indexDelete = SQLiteIndexLeafPage::deleteCellByRecordValuesWithOverflowRelease(
    $indexPage,
    $indexRecord,
    static fn (): array => [],
    $pageSize,
    secureDelete: true,
);
$pages[5] = $indexPage;

foreach ([
    3 => [SQLitePointerMapEntry::BTREE_PAGE, 7],
    4 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    5 => [SQLitePointerMapEntry::BTREE_PAGE, 8],
    7 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    8 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
] as $pageNumber => [$type, $parentPageNumber]) {
    $putPointerMapEntry($pageNumber, $type, $parentPageNumber);
}

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$plan = SQLiteBTreeEmptyLeafBatchFreePlan::fromDeleteResults($database, [
    ['leaf_page' => 3, 'leaf_page_type' => 'table-leaf', 'delete_result' => $tableDelete],
    ['leaf_page' => 5, 'leaf_page_type' => 'index-leaf', 'delete_result' => $indexDelete],
], true);
$summary = $plan->toArray();

echo 'Application transient delete rebalance promoted trunk page: ' . implode(',', $summary['new_trunk_page_numbers']) . PHP_EOL;
echo 'Application transient delete rebalance appended leaf page: ' . implode(',', $summary['leaf_page_numbers']) . PHP_EOL;
echo 'Application transient delete pointer-map pages: ' . implode(',', $summary['updated_pointer_map_page_numbers']) . PHP_EOL;
echo 'Application transient delete secure-cleared pages: ' . implode(',', $summary['secure_delete_cleared_pages']) . PHP_EOL;
