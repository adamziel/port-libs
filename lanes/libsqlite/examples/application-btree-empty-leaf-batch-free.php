<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeEmptyLeafBatchFreePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
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
$firstPage = substr_replace($firstPage, pack('N', 11), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 4), 32, 4);
$firstPage = substr_replace($firstPage, pack('N', 2), 36, 4);
$firstPage = substr_replace($firstPage, pack('N', 4), 52, 4);

$pointerMapPage = str_repeat("\0", $pageSize);
foreach ([
    3 => [SQLitePointerMapEntry::BTREE_PAGE, 7],
    4 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    5 => [SQLitePointerMapEntry::BTREE_PAGE, 8],
    6 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
    7 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    8 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    9 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 6],
    10 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    11 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 5],
] as $pageNumber => [$type, $parentPageNumber]) {
    $pointerMapPage = substr_replace($pointerMapPage, chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
}

$tablePayload = SQLiteRecord::encode([null, '_transient_batch_leaf', str_repeat('batched-option-fragment:', 54), 'no']);
$tableLocalLength = SQLiteTableLeafCell::localPayloadLength(strlen($tablePayload), $pageSize);
$tableOverflowPages = SQLiteOverflowPage::encodeChainAtPages(substr($tablePayload, $tableLocalLength), [6, 9], $pageSize);
$tablePage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(33, $tablePayload, $pageSize, 6),
], $pageSize);
$tableOverflowNumbers = static function (int $firstOverflowPage, int $byteCount) use ($tableOverflowPages): array {
    $pageNumbers = [];
    $pageNumber = $firstOverflowPage;
    $remaining = $byteCount;
    while ($pageNumber !== 0 && $remaining > 0) {
        $pageNumbers[] = $pageNumber;
        $pageNumber = unpack('N', substr($tableOverflowPages[$pageNumber], 0, 4))[1];
        $remaining -= min($remaining, 508);
    }

    return $pageNumbers;
};
$tableDelete = SQLiteTableLeafPage::deleteCellByRowIdWithOverflowRelease($tablePage, 33, $tableOverflowNumbers, secureDelete: true);

$indexKey = str_repeat('_transient_batch_idx_', 27);
$indexPayload = SQLiteRecord::encode([$indexKey, 33]);
$indexEncoded = SQLiteIndexCell::encodeWithOverflowPages($indexPayload, 11);
$indexOverflowPages = [11 => $indexEncoded['overflowPages'][0]];
$indexPage = SQLiteIndexLeafPage::assemble([$indexEncoded['cell']], $pageSize);
$indexOverflowReader = static function (int $firstOverflowPage, int $byteCount) use ($indexOverflowPages): string {
    $payload = '';
    $pageNumber = $firstOverflowPage;
    while ($pageNumber !== 0 && strlen($payload) < $byteCount) {
        $pageNumber = unpack('N', substr($indexOverflowPages[$pageNumber], 0, 4))[1];
        $payload .= substr($indexOverflowPages[11], 4);
    }

    return substr($payload, 0, $byteCount);
};
$indexDelete = SQLiteIndexLeafPage::deleteCellByRecordValuesWithOverflowRelease(
    $indexPage,
    [$indexKey, 33],
    static fn (int $firstOverflowPage, int $byteCount): array => range($firstOverflowPage, $firstOverflowPage + SQLiteOverflowPage::requiredPageCount($byteCount) - 1),
    secureDelete: true,
    overflowReader: $indexOverflowReader,
);

$database = SQLiteDatabase::fromBytes(
    $firstPage
    . $pointerMapPage
    . $tablePage
    . SQLiteFreelistTrunkPage::assemble(null, [10], $pageSize)
    . $indexPage
    . $tableOverflowPages[6]
    . $emptyPage
    . $emptyPage
    . $tableOverflowPages[9]
    . $emptyPage
    . $indexOverflowPages[11],
);

$plan = SQLiteBTreeEmptyLeafBatchFreePlan::fromDeleteResults($database, [
    ['leaf_page' => 3, 'leaf_page_type' => 'table-leaf', 'delete_result' => $tableDelete],
    ['leaf_page' => 5, 'leaf_page_type' => 'index-leaf', 'delete_result' => $indexDelete],
], true);

$pages = [];
for ($pageNumber = 1; $pageNumber <= 11; $pageNumber++) {
    $pages[$pageNumber] = $database->page($pageNumber);
}
foreach ($plan->pageImages as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}
$postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));

echo json_encode([
    'applicationUse' => 'Delete the last copied wp_options transient row and its option_name index entry, then batch-release both emptied leaves and obsolete overflow pages into the existing freelist with auto-vacuum pointer-map updates.',
    'emptyLeafBatchFreePlan' => $plan->toArray(),
    'freelistAfter' => $postDatabase->freelistPageNumbers(),
    'nextAllocationOrder' => $postDatabase->freelistAllocationOrder(),
    'pointerMapTypes' => [
        3 => $postDatabase->pointerMapEntryForPage(3)->typeName(),
        5 => $postDatabase->pointerMapEntryForPage(5)->typeName(),
        6 => $postDatabase->pointerMapEntryForPage(6)->typeName(),
        9 => $postDatabase->pointerMapEntryForPage(9)->typeName(),
        11 => $postDatabase->pointerMapEntryForPage(11)->typeName(),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
