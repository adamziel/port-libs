<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLitePointerMapEntry.php';
require_once __DIR__ . '/../src/SQLiteFreelistTrunkPage.php';
require_once __DIR__ . '/../src/SQLiteFreelistTraversalPlan.php';
require_once __DIR__ . '/../src/SQLiteFreelistFreePlan.php';
require_once __DIR__ . '/../src/SQLiteFreelistTruncatePlan.php';
require_once __DIR__ . '/../src/SQLiteBTreeFreeblock.php';
require_once __DIR__ . '/../src/SQLiteBTreePageHeader.php';
require_once __DIR__ . '/../src/SQLiteBTreeLeafPageCompactor.php';
require_once __DIR__ . '/../src/SQLiteVarint.php';
require_once __DIR__ . '/../src/SQLiteRecord.php';
require_once __DIR__ . '/../src/SQLiteTableLeafCell.php';
require_once __DIR__ . '/../src/SQLiteTableLeafPage.php';
require_once __DIR__ . '/../src/SQLiteBTreeDeleteRebalanceFreeblockApplyPlan.php';
require_once __DIR__ . '/../src/SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNext127Plan.php';
require_once __DIR__ . '/../src/SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteBTreePointerMapFreeblockVacuumCurrentSourceNext135Plan.php';
require_once __DIR__ . '/../src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext154Plan.php';

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext154Plan;
use PortLibs\LibSqlite\SQLiteDatabase;
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
    $page = substr_replace($page, pack('N', 110), 28, 4);
    $page = substr_replace($page, pack('N', 3), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMap = static function (array &$pages, int $pageNumber, int $type, int $parent): void {
    if ($pageNumber === 2 || $pageNumber === 105) {
        return;
    }

    $pointerMapPage = $pageNumber >= 106 ? 105 : 2;
    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage],
        chr($type) . pack('N', $parent),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$pages = array_fill(1, 110, str_repeat("\0", 512));
$pages[1] = $firstPage();
$pages[2] = str_repeat("\0", 512);
$pages[3] = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'active_plugins', 'a:1:{}'])),
    SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_timeout_next154', str_repeat('x', 96)])),
    SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', 'fresh'])),
]);
$pages[105] = str_repeat("\0", 512);
$pages[106] = pack('N', 107) . str_repeat('A', 508);
$pages[107] = pack('N', 108) . str_repeat('B', 508);
$pages[108] = pack('N', 109) . str_repeat('C', 508);
$pages[109] = pack('N', 110) . str_repeat('D', 508);
$pages[110] = pack('N', 0) . str_repeat('E', 508);

foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
    107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
    108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
    109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
    110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
] as $pageNumber => [$type, $parent]) {
    $putPointerMap($pages, $pageNumber, $type, $parent);
}

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);
$plan = SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext154Plan::tableLeafFromDeleteResult(
    $database,
    3,
    [
        'page' => $deletedPage,
        'rowid' => 2,
        'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
    ],
    4,
    true,
);

$summary = [
    'scenario' => 'wordpress-btree-vacuum-pointermap-freeblock-current-source-next154',
    'wordpress_use' => 'Delete a copied wp_options transient row, verify the current-source overflow next-pointer chain before freeblock/vacuum apply, and preserve only page 106 as the surviving freelist trunk after partial incremental vacuum.',
    'released_overflow_pages' => $plan->toArray()['released_overflow_pages'],
    'current_source_next_pages' => array_column($plan->currentSourceRows(), 'current_source_next_page'),
    'surviving_current_source_next_pages' => $plan->survivingCurrentSourceNextPages(),
    'mismatched_current_source_next_pages' => $plan->mismatchedCurrentSourceNextPages(),
    'freeblock_status' => $plan->freeblockSummary()['integrity_status'],
    'freeblock_count_after_compaction' => $plan->freeblockSummary()['freeblock_count'],
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    $ok = $summary['current_source_next_pages'] === [107, 108, 109, 110, 0]
        && $summary['surviving_current_source_next_pages'] === [106]
        && $summary['mismatched_current_source_next_pages'] === []
        && $summary['freeblock_status'] === 'ok'
        && $summary['freeblock_count_after_compaction'] === 0;
    if (!$ok) {
        fwrite(STDERR, "wordpress-btree-vacuum-pointermap-freeblock-current-source-next154 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "wordpress-btree-vacuum-pointermap-freeblock-current-source-next154 self-test passed\n");
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
