<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexOverflowRebalanceFreelistCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexInteriorPage;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;

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
$firstPage = substr_replace($firstPage, pack('N', 12), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 11), 32, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 36, 4);
$firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$pages = array_fill(1, 12, str_repeat("\0", $pageSize));
$pages[1] = $firstPage;
$pages[2] = str_repeat("\0", $pageSize);
$pages[11] = SQLiteFreelistTrunkPage::assemble(null, [], $pageSize);

$deletedValues = ['no', '_transient_rebalance_next82', str_repeat('wp-index-overflow:', 90), 10];
$encoded = SQLiteIndexCell::encodeWithOverflowPages(SQLiteRecord::encode($deletedValues), 7, $pageSize);
$overflowPages = array_combine(range(7, 6 + count($encoded['overflowPages'])), $encoded['overflowPages']);
$pages[3] = SQLiteIndexInteriorPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_alpha_next82', 30]), leftChildPage: 4),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['z', 'tail_divider_next82', 800]), leftChildPage: 5),
], 6);
$pages[4] = SQLiteIndexLeafPage::assemble([
    $encoded['cell'],
    SQLiteIndexCell::encode(SQLiteRecord::encode(['no', '_transient_keep_next82', 20])),
]);
$pages[5] = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_beta_next82', 40])),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_gamma_next82', 50])),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_omega_next82', 60])),
]);
$pages[6] = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['z', 'tail_next82', 900])),
]);
foreach ($overflowPages as $pageNumber => $page) {
    $pages[$pageNumber] = $page;
}

$putPointerMapEntry = static function (int $pageNumber, int $type, int $parentPageNumber) use (&$pages): void {
    if ($pageNumber === 2) {
        return;
    }
    $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};
foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    4 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
    5 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
    6 => [SQLitePointerMapEntry::BTREE_PAGE, 3],
    7 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4],
    8 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 7],
    9 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 8],
    10 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 9],
    11 => [SQLitePointerMapEntry::FREE_PAGE, 0],
] as $pageNumber => [$type, $parentPageNumber]) {
    $putPointerMapEntry($pageNumber, $type, $parentPageNumber);
}

$overflowReader = static function (int $firstOverflowPage, int $byteCount) use ($overflowPages): string {
    $payload = '';
    $pageNumber = $firstOverflowPage;
    while ($pageNumber !== 0 && strlen($payload) < $byteCount) {
        $page = $overflowPages[$pageNumber];
        $pageNumber = unpack('N', substr($page, 0, 4))[1];
        $payload .= substr($page, 4);
    }

    return substr($payload, 0, $byteCount);
};
$overflowNumbers = static function (int $firstOverflowPage, int $byteCount) use ($overflowPages): array {
    $pages = [];
    $pageNumber = $firstOverflowPage;
    $remaining = $byteCount;
    while ($pageNumber !== 0 && $remaining > 0) {
        $page = $overflowPages[$pageNumber];
        $pages[] = $pageNumber;
        $pageNumber = unpack('N', substr($page, 0, 4))[1];
        $remaining -= min($remaining, 508);
    }

    return $pages;
};

$plan = SQLiteBTreeIndexOverflowRebalanceFreelistCurrentSourceNextPlan::deleteFromLeftAndRebalanceRight(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    3,
    4,
    5,
    0,
    $deletedValues,
    $overflowNumbers,
    true,
    $overflowReader,
);

echo json_encode([
    'scenario' => 'wordpress-index-overflow-rebalance-freelist-next82',
    'deleted_option_name' => $deletedValues[1],
    'obsolete_overflow_pages' => $plan->obsoleteOverflowPageNumbers,
    'freelist_pages_after' => $plan->database->freelistPageNumbers(),
    'updated_page_numbers' => $plan->updatedPageNumbers(),
    'parent_divider_after' => $plan->toArray()['updated_parent_divider']['record_values'],
], JSON_PRETTY_PRINT) . "\n";
