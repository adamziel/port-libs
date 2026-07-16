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
require __DIR__ . '/../src/SQLiteBTreeFreeblockCoalescePlan.php';
require __DIR__ . '/../src/SQLiteOverflowFreelistReleasePlan.php';
require __DIR__ . '/../src/SQLiteBTreeOverflowPointerMapFreeblockCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteBTreeOverflowPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

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

$leafPage = static function (): string {
    $page = str_repeat("\xdd", 512);
    $page[0] = "\x0d";
    $page = substr_replace($page, pack('n', 390), 1, 2);
    $page = substr_replace($page, pack('n', 1), 3, 2);
    $page = substr_replace($page, pack('n', 376), 5, 2);
    $page[7] = chr(7);
    $page = substr_replace($page, pack('n', 496), 8, 2);
    $page = substr_replace($page, str_repeat('L', 12), 496, 12);
    $page = substr_replace($page, pack('n', 406) . pack('n', 12), 390, 4);
    $page = substr_replace($page, pack('n', 422) . pack('n', 14), 406, 4);
    $page = substr_replace($page, pack('n', 0) . pack('n', 18), 422, 4);

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
$pages[3] = $leafPage();
$pages[5] = pack('N', 6) . str_repeat('A', 508);
$pages[6] = pack('N', 7) . str_repeat('B', 508);
$pages[7] = pack('N', 0) . str_repeat('C', 508);
$pages[10] = SQLiteFreelistTrunkPage::assemble(null, [11, 12], 512);

foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    5 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
    6 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 5],
    7 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 6],
    10 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    11 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    12 => [SQLitePointerMapEntry::FREE_PAGE, 0],
] as $pageNumber => [$type, $parent]) {
    $putPointerMapEntry($pages, $pageNumber, $type, $parent);
}

$plan = SQLiteBTreeOverflowPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromCurrentSourceDeleteResult(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    3,
    [[
        'source' => 'wp_options transient timeout delete',
        'first_page' => 5,
        'overflow_payload_bytes' => 1524,
    ]],
    [
        'source' => 'wp_options transient timeout delete',
        'rowid' => 14201,
        'obsolete_overflow_page_numbers' => [5, 6, 7],
    ],
    3,
    str_repeat('Z', 1600),
    true,
);

$summary = [
    'scenario' => 'application-btree-overflow-pointermap-freeblock-current-source-next142',
    'application_use' => 'Delete a copied wp_options transient row, coalesce the leaf freeblock, release its obsolete overflow pages, and allocate the replacement overflow chain while preserving current-source pointer-map evidence.',
    'released_overflow_pages' => $plan->releasedOverflowPages(),
    'allocated_overflow_pages' => $plan->allocatedOverflowPages(),
    'reused_released_overflow_pages' => $plan->reusedReleasedOverflowPages(),
    'allocated_existing_freelist_pages' => $plan->allocatedExistingFreelistPages(),
    'final_freelist_page_numbers' => $plan->databaseAfterAllocation->freelistPageNumbers(),
    'current_source_pages' => array_column($plan->currentSourceRows(), 'page_number'),
    'next_pointer_map_types' => array_column($plan->nextRows(), 'next_pointer_map_type'),
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['released_overflow_pages'] !== [5, 6, 7]
        || $summary['allocated_overflow_pages'] !== [11, 7, 6, 5]
        || $summary['final_freelist_page_numbers'] !== [10, 12]
    ) {
        fwrite(STDERR, "application-btree-overflow-pointermap-freeblock-current-source-next142 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-btree-overflow-pointermap-freeblock-current-source-next142 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
