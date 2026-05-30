<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeInteriorFreelistRebalancePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteTableInteriorCell;
use PortLibs\LibSqlite\SQLiteTableInteriorPage;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$pageCount = 24;
$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', $pageCount), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);

$pointerMapPage = str_repeat("\0", $pageSize);
$setPointerMap = static function (int $pageNumber, int $type, int $parentPageNumber) use (&$pointerMapPage): void {
    $pointerMapPage = substr_replace($pointerMapPage, chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};
$setPointerMap(3, SQLitePointerMapEntry::ROOT_PAGE, 0);
$setPointerMap(4, SQLitePointerMapEntry::BTREE_PAGE, 3);
$setPointerMap(5, SQLitePointerMapEntry::BTREE_PAGE, 3);
$setPointerMap(6, SQLitePointerMapEntry::BTREE_PAGE, 3);
$setPointerMap(14, SQLitePointerMapEntry::BTREE_PAGE, 3);
$setPointerMap(20, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 14);
$setPointerMap(21, SQLitePointerMapEntry::OVERFLOW_PAGE, 20);

$interiorPage = SQLiteTableInteriorPage::assemble([
    SQLiteTableInteriorCell::encode(4, 5000),
    SQLiteTableInteriorCell::encode(5, 9000),
], 6, $pageSize);
$interiorPage = substr_replace($interiorPage, pack('n', 480), 1, 2);
$interiorPage = substr_replace($interiorPage, pack('n', 480), 5, 2);
$interiorPage = substr_replace($interiorPage, pack('n', 0) . pack('n', 16) . str_repeat("\0", 12), 480, 16);

$pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
$pages[1] = $firstPage;
$pages[2] = $pointerMapPage;
$pages[3] = $interiorPage;
ksort($pages);

$plan = SQLiteBTreeInteriorFreelistRebalancePlan::tableInteriorFromDeleteResult(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    3,
    [
        'page' => $interiorPage,
        'obsolete_child_page_numbers' => [14],
        'obsolete_overflow_page_numbers' => [20, 21],
    ],
    true,
);

echo json_encode($plan->toArray(), JSON_PRETTY_PRINT) . PHP_EOL;
