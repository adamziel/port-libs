<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreeFreeblockFreelistRebalancePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 512;
$pageCount = 207;
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
$pages[1] = substr_replace($pages[1], pack('N', 5), 32, 4);
$pages[1] = substr_replace($pages[1], pack('N', 2), 36, 4);
$pages[1] = substr_replace($pages[1], pack('N', 4), 52, 4);
$pages[1] = substr_replace($pages[1], pack('N', 1), 56, 4);
$pages[5] = SQLiteFreelistTrunkPage::assemble(null, [206], $pageSize);

$setPointerMap = static function (int $pageNumber, int $type, int $parentPageNumber) use (&$pages, $pageSize): void {
    $stride = intdiv($pageSize, 5) + 1;
    $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
    if ($pointerMapPage === $pageNumber) {
        return;
    }

    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage],
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$deletedPayload = SQLiteRecord::encode([null, '_transient_timeout_plugin_cache', str_repeat('wp-cache:', 260), 'no']);
$localLength = SQLiteTableLeafCell::localPayloadLength(strlen($deletedPayload), $pageSize);
$pages[3] = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(10, $deletedPayload, $pageSize, 8),
    SQLiteTableLeafCell::encode(20, SQLiteRecord::encode([null, 'siteurl', 'https://example.test', 'yes']), $pageSize),
], $pageSize);
foreach (SQLiteOverflowPage::encodeChainAtPages(substr($deletedPayload, $localLength), [8, 104, 107, 205], $pageSize) as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}

$setPointerMap(3, SQLitePointerMapEntry::ROOT_PAGE, 0);
$setPointerMap(5, SQLitePointerMapEntry::FREE_PAGE, 0);
$setPointerMap(206, SQLitePointerMapEntry::FREE_PAGE, 0);
$setPointerMap(8, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
$setPointerMap(104, SQLitePointerMapEntry::OVERFLOW_PAGE, 8);
$setPointerMap(107, SQLitePointerMapEntry::OVERFLOW_PAGE, 104);
$setPointerMap(205, SQLitePointerMapEntry::OVERFLOW_PAGE, 107);

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$delete = SQLiteTableLeafPage::deleteCellByRowIdWithOverflowRelease(
    $database->page(3),
    10,
    static fn (int $firstOverflowPage, int $byteCount): array => $database->overflowPageChainNumbers($firstOverflowPage, $byteCount),
    $pageSize,
    0,
    null,
    true,
);
$plan = SQLiteBTreeFreeblockFreelistRebalancePlan::tableLeafFromDeleteResult($database, 3, $delete, true);

echo json_encode([
    'applicationUse' => 'Delete an obsolete oversized wp_options transient and release its overflow pages into the freelist while clearing auto-vacuum pointer-map ownership.',
    'freedOverflowPages' => $plan->obsoleteOverflowPageNumbers,
    'updatedPointerMapPages' => array_keys($plan->freePlan->updatedPointerMapPages),
    'freedPointerMapEntries' => $plan->freePlan->freedPointerMapEntries,
    'freelistPagesAfterDelete' => $plan->freePlan->freedPageNumbers,
    'secureDeleteClearedPages' => $plan->freePlan->clearedPageNumbers,
], JSON_PRETTY_PRINT) . PHP_EOL;
