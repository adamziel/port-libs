<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLitePointerMapEntry.php';
require_once __DIR__ . '/../src/SQLiteFreelistTrunkPage.php';
require_once __DIR__ . '/../src/SQLiteFreelistTraversalPlan.php';
require_once __DIR__ . '/../src/SQLiteFreelistFreePlan.php';
require_once __DIR__ . '/../src/SQLiteFreelistTruncatePlan.php';
require_once __DIR__ . '/../src/SQLiteFreelistAllocationPlan.php';
require_once __DIR__ . '/../src/SQLiteBTreeFreeblock.php';
require_once __DIR__ . '/../src/SQLiteBTreePageHeader.php';
require_once __DIR__ . '/../src/SQLiteBTreeLeafPageCompactor.php';
require_once __DIR__ . '/../src/SQLiteVarint.php';
require_once __DIR__ . '/../src/SQLiteRecord.php';
require_once __DIR__ . '/../src/SQLiteTableLeafCell.php';
require_once __DIR__ . '/../src/SQLiteTableLeafPage.php';
require_once __DIR__ . '/../src/SQLiteOverflowPage.php';
require_once __DIR__ . '/../src/SQLiteBTreeDeleteRebalanceFreeblockApplyPlan.php';
require_once __DIR__ . '/../src/SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteBTreePointerMapFreeblockVacuumCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan.php';
require_once __DIR__ . '/../src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan.php';

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage = static function (int $pageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', 3), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2 || $pageNumber === 105) {
        return;
    }

    $pointerMapPage = $pageNumber >= 106 ? 105 : 2;
    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage],
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$pages = array_fill(1, 110, str_repeat("\0", 512));
$pages[1] = $makeFirstPage(110);
$pages[2] = str_repeat("\0", 512);
$pages[3] = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'active_plugins', 'a:1:{}'])),
    SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next159', str_repeat('x', 96)])),
    SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('r', 24)])),
]);
$pages[105] = str_repeat("\0", 512);
foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
    $pages[$pageNumber] = pack('N', $nextPage) . str_repeat('T', 508);
}

foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
    107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
    108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
    109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
    110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
] as $pageNumber => [$type, $parent]) {
    $putPointerMapEntry($pages, $pageNumber, $type, $parent);
}

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);
$plan = SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan::tableLeafReuseAuditFromDeleteResult(
    $database,
    3,
    [
        'page' => $deletedPage,
        'rowid' => 2,
        'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
    ],
    3,
    str_repeat('rewritten-wp-option-next159-', 24),
    3,
    true,
);

$summary = [
    'scenario' => 'application-btree-vacuum-pointermap-freeblock-current-source-next159',
    'applicationUse' => 'Delete an obsolete copied wp_options transient, vacuum tail overflow pages, then reuse the surviving freelist leaf and trunk as a multi-page replacement overflow chain with corrected auto-vacuum pointer-map ownership.',
    'allocated_overflow_pages' => $plan->basePlan->allocatedOverflowPages(),
    'reused_surviving_chain_pages' => $plan->reusedSurvivingChainPages(),
    'rejected_truncated_chain_pages' => $plan->rejectedTruncatedChainPages(),
    'final_overflow_next_pages' => array_column($plan->chainRows(), 'final_overflow_next_page'),
    'final_pointer_map_types' => array_column($plan->chainRows(), 'final_pointer_map_type'),
    'final_pointer_map_parents' => array_column($plan->chainRows(), 'final_pointer_map_parent'),
    'final_freelist_page_numbers' => $plan->basePlan->databaseAfterAllocation->freelistPageNumbers(),
];

if (($argv[1] ?? null) === '--self-test') {
    $expected = [
        'allocated_overflow_pages' => [107, 106],
        'reused_surviving_chain_pages' => [106, 107],
        'rejected_truncated_chain_pages' => [108, 109, 110],
        'final_overflow_next_pages' => [0, 106, null, null, null],
        'final_pointer_map_types' => ['overflow-page', 'first-overflow-page', null, null, null],
        'final_pointer_map_parents' => [107, 3, null, null, null],
        'final_freelist_page_numbers' => [],
    ];
    foreach ($expected as $key => $value) {
        if ($summary[$key] !== $value) {
            fwrite(STDERR, "application-btree-vacuum-pointermap-freeblock-current-source-next159 self-test failed\n");
            exit(1);
        }
    }

    fwrite(STDOUT, "application-btree-vacuum-pointermap-freeblock-current-source-next159 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
