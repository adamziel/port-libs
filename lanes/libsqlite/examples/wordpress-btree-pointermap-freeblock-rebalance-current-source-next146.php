<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteHeader.php';
require __DIR__ . '/../src/SQLiteDatabase.php';
require __DIR__ . '/../src/SQLitePointerMapEntry.php';
require __DIR__ . '/../src/SQLiteFreelistTrunkPage.php';
require __DIR__ . '/../src/SQLiteFreelistTraversalPlan.php';
require __DIR__ . '/../src/SQLiteFreelistFreePlan.php';
require __DIR__ . '/../src/SQLiteFreelistAllocationPlan.php';
require __DIR__ . '/../src/SQLiteOverflowPage.php';
require __DIR__ . '/../src/SQLiteBTreeFreeblock.php';
require __DIR__ . '/../src/SQLiteBTreePageHeader.php';
require __DIR__ . '/../src/SQLiteBTreeLeafPageCompactor.php';
require __DIR__ . '/../src/SQLiteVarint.php';
require __DIR__ . '/../src/SQLiteBlobValue.php';
require __DIR__ . '/../src/SQLiteRecord.php';
require __DIR__ . '/../src/SQLiteTableLeafCell.php';
require __DIR__ . '/../src/SQLiteTableLeafPage.php';
require __DIR__ . '/../src/SQLiteBTreeDeleteRebalanceFreeblockApplyPlan.php';
require __DIR__ . '/../src/SQLiteBTreePointerMapFreeblockRebalanceCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteBTreePointerMapFreeblockRebalanceCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$firstPage = static function (): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', 12), 28, 4);
    $page = substr_replace($page, pack('N', 10), 32, 4);
    $page = substr_replace($page, pack('N', 3), 36, 4);
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2) {
        return;
    }

    $pages[2] = substr_replace(
        $pages[2],
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - 3),
        5,
    );
};

$pages = array_fill(1, 12, str_repeat("\0", 512));
$pages[1] = $firstPage();
$pages[2] = str_repeat("\0", 512);
$pages[3] = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(101, SQLiteRecord::encode([null, 'autoload', 'yes'])),
    SQLiteTableLeafCell::encode(102, SQLiteRecord::encode([null, '_transient_rewrite_rules', str_repeat('r', 190)])),
    SQLiteTableLeafCell::encode(103, SQLiteRecord::encode([null, '_site_transient_update_core', 'fresh'])),
]);
$pages[6] = pack('N', 7) . str_repeat('A', 508);
$pages[7] = pack('N', 0) . str_repeat('B', 508);
$pages[10] = SQLiteFreelistTrunkPage::assemble(null, [11, 12], 512);

foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    6 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
    7 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 6],
    10 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    11 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    12 => [SQLitePointerMapEntry::FREE_PAGE, 0],
] as $pageNumber => [$type, $parent]) {
    $putPointerMapEntry($pages, $pageNumber, $type, $parent);
}

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$plan = SQLiteBTreePointerMapFreeblockRebalanceCurrentSourceNextPlan::tableLeafFromCurrentSourceDeleteResult(
    $database,
    3,
    [
        'page' => SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 102, secureDelete: true),
        'rowid' => 102,
        'obsolete_overflow_page_numbers' => [6, 7],
    ],
    3,
    str_repeat('Z', 1200),
    true,
);

$summary = [
    'scenario' => 'wordpress-btree-pointermap-freeblock-rebalance-current-source-next146',
    'wordpress_use' => 'Delete an oversized copied wp_options transient, apply the leaf freeblock rebalance, free its obsolete overflow pages, and reuse them in the replacement overflow chain with fresh auto-vacuum pointer-map parents.',
    'deleted_rowids' => $plan->rebalancePlan->deletedRowIds,
    'freeblock_bytes_after' => $plan->rebalancePlan->freeblockBytesAfter,
    'released_overflow_pages' => $plan->releasedOverflowPages(),
    'allocated_overflow_pages' => $plan->allocatedOverflowPages(),
    'reused_released_overflow_pages' => $plan->reusedReleasedOverflowPages(),
    'next_pointer_map_types' => array_column($plan->nextRows(), 'next_pointer_map_type'),
    'next_pointer_map_parents' => array_column($plan->nextRows(), 'next_pointer_map_parent'),
    'final_freelist_page_numbers' => $plan->databaseAfterAllocation->freelistPageNumbers(),
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['deleted_rowids'] !== [102]
        || $summary['freeblock_bytes_after'] !== 0
        || $summary['allocated_overflow_pages'] !== [11, 7, 6]
        || $summary['next_pointer_map_parents'] !== [3, 11, 7]
        || $summary['final_freelist_page_numbers'] !== [10, 12]
    ) {
        fwrite(STDERR, "wordpress-btree-pointermap-freeblock-rebalance-current-source-next146 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-btree-pointermap-freeblock-rebalance-current-source-next146 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
