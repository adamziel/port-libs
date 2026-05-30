<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowCellReuseDeleteApplyPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$pageCount = 16;
$emptyPage = str_repeat("\0", $pageSize);
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
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);
$firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);

$pointerMapPage = str_repeat("\0", $pageSize);
$setPointerMap = static function (int $pageNumber, int $type, int $parentPageNumber) use (&$pointerMapPage): void {
    $pointerMapPage = substr_replace($pointerMapPage, chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};
$setPointerMap(3, SQLitePointerMapEntry::ROOT_PAGE, 0);
$setPointerMap(11, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
$setPointerMap(6, SQLitePointerMapEntry::OVERFLOW_PAGE, 11);
$setPointerMap(14, SQLitePointerMapEntry::OVERFLOW_PAGE, 6);

$keptPayload = SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes']);
$oldPayload = SQLiteRecord::encode([null, '_transient_next_pointer_old', str_repeat('overflow-next-pointer:', 75), 'no']);
$newPayload = SQLiteRecord::encode([null, '_transient_next_pointer_new', 'fresh-cache', 'no']);
$oldLocalLength = SQLiteTableLeafCell::localPayloadLength(strlen($oldPayload), $pageSize);
$overflowPages = SQLiteOverflowPage::encodeChainAtPages(substr($oldPayload, $oldLocalLength), [11, 6, 14], $pageSize);
$leafPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, $keptPayload),
    SQLiteTableLeafCell::encode(7, $oldPayload, $pageSize, 11),
], $pageSize);

$pages = [
    1 => $firstPage,
    2 => $pointerMapPage,
    3 => $leafPage,
];
for ($pageNumber = 4; $pageNumber <= $pageCount; $pageNumber++) {
    $pages[$pageNumber] = $overflowPages[$pageNumber] ?? $emptyPage;
}
ksort($pages);

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$overflowNumbers = static fn (int $firstOverflowPage, int $byteCount): array => SQLiteOverflowPage::pageNumbersFromDatabase($database, $firstOverflowPage, $byteCount);
$plan = SQLiteBTreeOverflowCellReuseDeleteApplyPlan::tableCell(
    $database,
    3,
    $leafPage,
    7,
    8,
    $newPayload,
    $overflowNumbers,
    true,
);

echo json_encode([
    'applicationUse' => 'Delete a copied wp_options transient whose overflow chain was moved by auto-vacuum, follow the on-page next pointers, reuse the freed local cell slot, and release obsolete overflow pages into the freelist.',
    'plan' => $plan->toArray(),
], JSON_PRETTY_PRINT) . PHP_EOL;
