<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeDeleteRebalanceFreelistCurrentSourcePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstPage = static function (): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', 150), 28, 4);
    $page = substr_replace($page, pack('N', 5), 32, 4);
    $page = substr_replace($page, pack('N', 120), 36, 4);
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber !== 2 && $pageNumber !== 104) {
        $pointerMapPage = $pageNumber < 104 ? 2 : 104;
        $offset = 5 * ($pageNumber - $pointerMapPage - 1);
        $pages[$pointerMapPage] = substr_replace($pages[$pointerMapPage], chr($type) . pack('N', $parentPageNumber), $offset, 5);
    }
};

$pages = array_fill(1, 150, str_repeat("\0", 512));
$pages[1] = $firstPage();
$pages[2] = str_repeat("\0", 512);
$pages[3] = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'autoload', 'yes'])),
    SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_cache_a', str_repeat('a', 124)])),
    SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, '_transient_cache_b', str_repeat('b', 122)])),
    SQLiteTableLeafCell::encode(4, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
]);
$existingFreelistLeaves = array_merge(range(20, 103), range(105, 139));
$pages[5] = SQLiteFreelistTrunkPage::assemble(null, $existingFreelistLeaves, 512, 512);
$pages[6] = str_repeat('x', 512);
$pages[7] = str_repeat('y', 512);

$putPointerMapEntry($pages, 3, SQLitePointerMapEntry::BTREE_PAGE, 4);
$putPointerMapEntry($pages, 4, SQLitePointerMapEntry::ROOT_PAGE, 0);
$putPointerMapEntry($pages, 5, SQLitePointerMapEntry::FREE_PAGE, 0);
$putPointerMapEntry($pages, 6, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
$putPointerMapEntry($pages, 7, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
foreach ($existingFreelistLeaves as $pageNumber) {
    $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0);
}

$plan = SQLiteBTreeDeleteRebalanceFreelistCurrentSourcePlan::tableLeaf(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    3,
    [
        ['rowid' => 2, 'obsolete_overflow_page_numbers' => [6]],
        ['rowid' => 3, 'obsolete_overflow_page_numbers' => [7]],
    ],
    true,
);

echo json_encode([
    'scenario' => 'application-btree-delete-rebalance-freelist-current-source-next100',
    'deleted_transients' => ['_transient_cache_a', '_transient_cache_b'],
    'released_overflow_pages' => $plan->releasedOverflowPageNumbers(),
    'final_freelist_page_count' => $plan->finalFreelistPageCount(),
    'final_freelist_trunk_pages' => $plan->finalFreelistTrunkPages(),
    'final_freelist_allocation_order_head' => array_slice($plan->finalFreelistAllocationOrder(), 0, 6),
    'materialized_page_numbers' => $plan->materializedPageNumbers(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
