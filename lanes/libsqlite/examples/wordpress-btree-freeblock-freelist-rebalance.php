<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeFreeblockFreelistRebalancePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
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
$firstPage = substr_replace($firstPage, pack('N', 8), 32, 4);
$firstPage = substr_replace($firstPage, pack('N', 2), 36, 4);
$firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);

$pointerMapPage = str_repeat("\0", $pageSize);
$setPointerMap = static function (int $pageNumber, int $type, int $parentPageNumber) use (&$pointerMapPage): void {
    $pointerMapPage = substr_replace($pointerMapPage, chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};
$setPointerMap(3, SQLitePointerMapEntry::ROOT_PAGE, 0);
$setPointerMap(4, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
$setPointerMap(8, SQLitePointerMapEntry::FREE_PAGE, 0);
$setPointerMap(16, SQLitePointerMapEntry::FREE_PAGE, 0);

$keptPayload = SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes']);
$deletedPayload = SQLiteRecord::encode([null, '_transient_rebalance_demo', str_repeat('fragment:', 90), 'no']);
$deletedLocalLength = SQLiteTableLeafCell::localPayloadLength(strlen($deletedPayload), $pageSize);
$overflowPages = SQLiteOverflowPage::encodeChainAtPages(substr($deletedPayload, $deletedLocalLength), [4], $pageSize);
$tablePage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, $keptPayload),
    SQLiteTableLeafCell::encode(2, $deletedPayload, $pageSize, 4),
], $pageSize);
$overflowNumbers = static function (int $firstOverflowPage, int $byteCount) use ($overflowPages): array {
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

$delete = SQLiteTableLeafPage::deleteCellByRowIdWithOverflowRelease($tablePage, 2, $overflowNumbers, secureDelete: true);
$pages = [
    1 => $firstPage,
    2 => $pointerMapPage,
    3 => $tablePage,
    4 => $overflowPages[4],
    5 => $emptyPage,
    6 => $emptyPage,
    7 => $emptyPage,
    8 => SQLiteFreelistTrunkPage::assemble(null, [16], $pageSize),
];
for ($pageNumber = 9; $pageNumber <= $pageCount; $pageNumber++) {
    $pages[$pageNumber] = $emptyPage;
}
ksort($pages);

$plan = SQLiteBTreeFreeblockFreelistRebalancePlan::tableLeafFromDeleteResult(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    3,
    $delete,
    true,
);

echo json_encode($plan->toArray(), JSON_PRETTY_PRINT) . PHP_EOL;
