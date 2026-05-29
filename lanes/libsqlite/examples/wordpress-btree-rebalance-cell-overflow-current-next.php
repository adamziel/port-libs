<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeFreeblockRebalanceCellOverflowCurrentNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableInteriorCell;
use PortLibs\LibSqlite\SQLiteTableInteriorPage;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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
$firstPage = substr_replace($firstPage, pack('N', 8), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$payload = static fn (string $name, string $value = 'yes'): string => SQLiteRecord::encode([null, $name, $value, 'yes']);
$largePayload = SQLiteRecord::encode([null, '_transient_plugin_cache_old', str_repeat('stale-plugin-cache:', 62), 'no']);
$large = SQLiteTableLeafCell::encodeWithOverflowPages(10, $largePayload, 7, $pageSize);
$leftLeaf = SQLiteTableLeafPage::assemble([
    $large['cell'],
    SQLiteTableLeafCell::encode(20, $payload('_transient_plugin_cache_keep'), $pageSize),
], $pageSize);
$rightLeaf = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(30, $payload('autoload_plugin_cache_30'), $pageSize),
    SQLiteTableLeafCell::encode(40, $payload('autoload_plugin_cache_40'), $pageSize),
    SQLiteTableLeafCell::encode(50, $payload('autoload_plugin_cache_50'), $pageSize),
    SQLiteTableLeafCell::encode(60, $payload('autoload_plugin_cache_60'), $pageSize),
], $pageSize);
$tailLeaf = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(9000, $payload('tail_plugin_cache'), $pageSize),
], $pageSize);
$parent = SQLiteTableInteriorPage::assemble([
    SQLiteTableInteriorCell::encode(4, 20),
    SQLiteTableInteriorCell::encode(5, 8999),
], 6, $pageSize);

$pointerMapPage = str_repeat("\0", $pageSize);
foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    4 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
    5 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
    6 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
    7 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4],
    8 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 7],
] as $pageNumber => [$type, $parentPageNumber]) {
    $pointerMapPage = substr_replace($pointerMapPage, chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
}

$database = SQLiteDatabase::fromBytes(
    $firstPage
    . $pointerMapPage
    . $parent
    . $leftLeaf
    . $rightLeaf
    . $tailLeaf
    . implode('', $large['overflowPages']),
);
$overflowPages = array_combine(range(7, 6 + count($large['overflowPages'])), $large['overflowPages']);
$overflowPageNumbers = static function (int $firstOverflowPage, int $byteCount) use ($overflowPages): array {
    $pageNumbers = [];
    $pageNumber = $firstOverflowPage;
    $remaining = $byteCount;
    while ($pageNumber !== 0 && $remaining > 0) {
        $pageNumbers[] = $pageNumber;
        $page = $overflowPages[$pageNumber];
        $pageNumber = unpack('N', substr($page, 0, 4))[1];
        $remaining -= min($remaining, 508);
    }

    return $pageNumbers;
};

$plan = SQLiteBTreeFreeblockRebalanceCellOverflowCurrentNextPlan::tableDeleteRebalanceThenReplaceOverflow(
    $database,
    3,
    4,
    5,
    0,
    10,
    $overflowPageNumbers,
    str_repeat('fresh-plugin-cache:', 64),
    true,
    true,
);

$pointerMapTypes = [];
foreach ($plan->replacementOverflowPageNumbers() as $pageNumber) {
    $pointerMapTypes[$pageNumber] = $plan->database->pointerMapEntryForPage($pageNumber)->typeName();
}

echo json_encode([
    'wordpressUse' => 'After deleting an overflow-backed copied wp_options transient, rebalance the current/next leaf pages and reuse the obsolete overflow pages for the next replacement overflow chain.',
    'btreeFreeblockRebalanceCellOverflowCurrentNext' => $plan->toArray(),
    'freelistAfterReplacement' => $plan->database->freelistPageNumbers(),
    'pointerMapTypes' => $pointerMapTypes,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
