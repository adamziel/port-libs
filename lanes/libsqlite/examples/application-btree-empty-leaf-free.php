<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeEmptyLeafFreePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
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
$firstPage = substr_replace($firstPage, pack('N', 6), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 4), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$pointerMapPage = str_repeat("\0", $pageSize);
foreach ([3 => [SQLitePointerMapEntry::BTREE_PAGE, 4], 4 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 5 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3], 6 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 5]] as $pageNumber => [$type, $parentPageNumber]) {
    $pointerMapPage = substr_replace($pointerMapPage, chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
}

$payload = SQLiteRecord::encode([null, '_transient_deleted_leaf', str_repeat('cached-option-fragment:', 62), 'no']);
$localLength = SQLiteTableLeafCell::localPayloadLength(strlen($payload), $pageSize);
$overflowPages = SQLiteOverflowPage::encodeChainAtPages(substr($payload, $localLength), [5, 6], $pageSize);
$tablePage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(22, $payload, $pageSize, 5),
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

$delete = SQLiteTableLeafPage::deleteCellByRowIdWithOverflowRelease($tablePage, 22, $overflowNumbers, secureDelete: true);
$database = SQLiteDatabase::fromBytes($firstPage . $pointerMapPage . $tablePage . $emptyPage . $overflowPages[5] . $overflowPages[6]);
$plan = SQLiteBTreeEmptyLeafFreePlan::tableLeafFromDeleteResult($database, 3, $delete, true);

$pages = [1 => $database->page(1), 2 => $database->page(2), 3 => $database->page(3), 4 => $database->page(4), 5 => $database->page(5), 6 => $database->page(6)];
foreach ($plan->pageImages as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}
$postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));

echo json_encode([
    'applicationUse' => 'Delete the final copied wp_options transient from a non-root leaf, then release the empty leaf and its obsolete overflow pages into the freelist with auto-vacuum pointer-map free-page updates.',
    'emptyLeafFreePlan' => $plan->toArray(),
    'freelistAfter' => $postDatabase->freelistPageNumbers(),
    'nextAllocationOrder' => $postDatabase->freelistAllocationOrder(),
    'pointerMapTypes' => [
        3 => $postDatabase->pointerMapEntryForPage(3)->typeName(),
        5 => $postDatabase->pointerMapEntryForPage(5)->typeName(),
        6 => $postDatabase->pointerMapEntryForPage(6)->typeName(),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
