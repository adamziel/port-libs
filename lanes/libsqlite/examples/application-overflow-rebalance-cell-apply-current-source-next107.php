<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$pageCount = 14;
$pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
$pages[1] = str_repeat("\0", $pageSize);
$pages[1] = substr_replace($pages[1], "SQLite format 3\0", 0, 16);
$pages[1] = substr_replace($pages[1], pack('n', $pageSize), 16, 2);
$pages[1][18] = "\x01";
$pages[1][19] = "\x01";
$pages[1][20] = "\x00";
$pages[1][21] = "\x40";
$pages[1][22] = "\x20";
$pages[1][23] = "\x20";
$pages[1] = substr_replace($pages[1], pack('N', $pageCount), 28, 4);
$pages[1] = substr_replace($pages[1], pack('N', 3), 52, 4);
$pages[1] = substr_replace($pages[1], pack('N', 1), 56, 4);
$pages[2] = str_repeat("\0", $pageSize);

$setPointerMap = static function (int $pageNumber, int $type, int $parentPageNumber) use (&$pages): void {
    $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};

$keptPayload = SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes']);
$oldPayload = SQLiteRecord::encode([null, '_transient_feed_107', str_repeat('cached-feed:', 90), 'no']);
$newPayload = SQLiteRecord::encode([null, '_transient_feed_107', 'fresh-feed-cache', 'no']);
$localLength = SQLiteTableLeafCell::localPayloadLength(strlen($oldPayload), $pageSize);
$overflowPages = SQLiteOverflowPage::encodeChainAtPages(substr($oldPayload, $localLength), [8, 5], $pageSize);
$pages[3] = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, $keptPayload),
    SQLiteTableLeafCell::encode(20, $oldPayload, $pageSize, 8),
]);
foreach ($overflowPages as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}
$setPointerMap(3, SQLitePointerMapEntry::ROOT_PAGE, 0);
$setPointerMap(8, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
$setPointerMap(5, SQLitePointerMapEntry::OVERFLOW_PAGE, 8);
ksort($pages);

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$plan = SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan::tableLeafCurrentSource(
    $database,
    3,
    20,
    21,
    $newPayload,
    static fn (int $firstPage, int $byteCount): array => SQLiteOverflowPage::pageNumbersFromDatabase($database, $firstPage, $byteCount),
    true,
);

echo json_encode([
    'scenario' => 'application-overflow-rebalance-cell-apply-current-source-next107',
    'applicationUse' => 'Apply a copied wp_options transient replacement to the current table leaf after deleting the old overflow-backed cell, then materialize obsolete overflow pages into the freelist and pointer map.',
    'summary' => $plan->toArray(),
], JSON_PRETTY_PRINT) . PHP_EOL;
