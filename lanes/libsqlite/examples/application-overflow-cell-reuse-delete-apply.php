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
$pageCount = 12;
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
$firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);

$pointerMapPage = str_repeat("\0", $pageSize);
$setPointerMap = static function (int $pageNumber, int $type, int $parentPageNumber) use (&$pointerMapPage): void {
    $pointerMapPage = substr_replace($pointerMapPage, chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};
$setPointerMap(3, SQLitePointerMapEntry::ROOT_PAGE, 0);
$setPointerMap(4, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
$setPointerMap(5, SQLitePointerMapEntry::OVERFLOW_PAGE, 4);

$keptPayload = SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes']);
$oldPayload = SQLiteRecord::encode([null, '_transient_reuse_old', str_repeat('cached-widget:', 90), 'no']);
$newPayload = SQLiteRecord::encode([null, '_transient_reuse_new', 'fresh-cache', 'no']);
$oldLocalLength = SQLiteTableLeafCell::localPayloadLength(strlen($oldPayload), $pageSize);
$overflowPages = SQLiteOverflowPage::encodeChainAtPages(substr($oldPayload, $oldLocalLength), [4, 5], $pageSize);
$leafPage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, $keptPayload),
    SQLiteTableLeafCell::encode(7, $oldPayload, $pageSize, 4),
], $pageSize);
$pages = [
    1 => $firstPage,
    2 => $pointerMapPage,
    3 => $leafPage,
    4 => $overflowPages[4],
    5 => $overflowPages[5],
];
for ($pageNumber = 6; $pageNumber <= $pageCount; $pageNumber++) {
    $pages[$pageNumber] = $emptyPage;
}
ksort($pages);

$overflowNumbers = static function (int $firstOverflowPage, int $byteCount) use ($overflowPages): array {
    $numbers = [];
    $pageNumber = $firstOverflowPage;
    $remaining = $byteCount;
    while ($pageNumber !== 0 && $remaining > 0) {
        $numbers[] = $pageNumber;
        $pageNumber = unpack('N', substr($overflowPages[$pageNumber], 0, 4))[1];
        $remaining -= 508;
    }

    return $numbers;
};

$plan = SQLiteBTreeOverflowCellReuseDeleteApplyPlan::tableCell(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    3,
    $leafPage,
    7,
    8,
    $newPayload,
    $overflowNumbers,
    true,
);

echo json_encode([
    'applicationUse' => 'Replace a deleted overflow-backed wp_options transient with a smaller local cell in the same leaf freeblock while releasing obsolete overflow pages to the freelist.',
    'plan' => $plan->toArray(),
], JSON_PRETTY_PRINT) . PHP_EOL;
