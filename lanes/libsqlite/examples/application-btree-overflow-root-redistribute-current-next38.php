<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreeOverflowRootRedistributePlan;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableInteriorCell;
use PortLibs\LibSqlite\SQLiteTableInteriorPage;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;
use PortLibs\LibSqlite\SQLiteDatabase;

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
$firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$pointerMap = str_repeat("\0", $pageSize);
foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    4 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
    5 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
    6 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
    7 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4],
    8 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 7],
    9 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 8],
] as $pageNumber => $entry) {
    $pointerMap = substr_replace($pointerMap, chr($entry[0]) . pack('N', $entry[1]), 5 * ($pageNumber - 3), 5);
}

$payload = static fn (string $name, string $value, string $autoload): string => SQLiteRecord::encode([null, $name, $value, $autoload]);
$largePayload = $payload('_transient_import_cache', str_repeat('application-cache-page:', 80), 'no');
$large = SQLiteTableLeafCell::encodeWithOverflowPages(10, $largePayload, 7, $pageSize);
$root = SQLiteTableInteriorPage::assemble([
    SQLiteTableInteriorCell::encode(4, 20),
    SQLiteTableInteriorCell::encode(5, 8999),
], 6, $pageSize);
$currentLeaf = SQLiteTableLeafPage::assemble([
    $large['cell'],
    SQLiteTableLeafCell::encode(20, $payload('_transient_keep', 'yes', 'no'), $pageSize),
], $pageSize);
$nextLeaf = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(30, $payload('autoload_plugin_a', 'yes', 'yes'), $pageSize),
    SQLiteTableLeafCell::encode(40, $payload('autoload_plugin_b', 'yes', 'yes'), $pageSize),
    SQLiteTableLeafCell::encode(50, $payload('autoload_plugin_c', 'yes', 'yes'), $pageSize),
    SQLiteTableLeafCell::encode(60, $payload('autoload_plugin_d', 'yes', 'yes'), $pageSize),
], $pageSize);
$tailLeaf = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(9000, $payload('tail_option', 'tail', 'yes'), $pageSize),
], $pageSize);

$database = SQLiteDatabase::fromBytes($firstPage . $pointerMap . $root . $currentLeaf . $nextLeaf . $tailLeaf . implode('', $large['overflowPages']));
$overflowPages = array_combine(range(7, 6 + count($large['overflowPages'])), $large['overflowPages']);
$overflowPageNumbers = static function (int $firstOverflowPage, int $byteCount) use ($overflowPages): array {
    $pageNumbers = [];
    $pageNumber = $firstOverflowPage;
    $remaining = $byteCount;
    while ($pageNumber !== 0 && $remaining > 0) {
        $pageNumbers[] = $pageNumber;
        $pageNumber = unpack('N', substr($overflowPages[$pageNumber], 0, 4))[1];
        $remaining -= min($remaining, 508);
    }

    return $pageNumbers;
};

$plan = SQLiteBTreeOverflowRootRedistributePlan::deleteCurrentAndRedistributeNext(
    $database,
    3,
    4,
    5,
    0,
    10,
    $overflowPageNumbers,
    true,
);

echo json_encode($plan->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
