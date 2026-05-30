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
require_once __DIR__ . '/../src/SQLiteIndexCell.php';
require_once __DIR__ . '/../src/SQLiteIndexLeafPage.php';
require_once __DIR__ . '/../src/SQLiteBTreeDeleteRebalanceFreeblockApplyPlan.php';
require_once __DIR__ . '/../src/SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan;
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
    $page = substr_replace($page, pack('N', 10), 28, 4);
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMap = static function (array &$pages, int $pageNumber, int $type, int $parent): void {
    if ($pageNumber === 2) {
        return;
    }
    $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
};

$pages = array_fill(1, 10, str_repeat("\0", 512));
$pages[1] = $firstPage();
$pages[2] = str_repeat("\0", 512);
$pages[3] = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(10, SQLiteRecord::encode([null, 'active_plugins', 'a:1:{}'])),
    SQLiteTableLeafCell::encode(11, SQLiteRecord::encode([null, '_transient_timeout_next127', str_repeat('x', 120)])),
    SQLiteTableLeafCell::encode(12, SQLiteRecord::encode([null, 'rewrite_rules', 'fresh'])),
]);
$pages[7] = pack('N', 8) . str_repeat('A', 508);
$pages[8] = pack('N', 9) . str_repeat('B', 508);
$pages[9] = pack('N', 10) . str_repeat('C', 508);
$pages[10] = pack('N', 0) . str_repeat('D', 508);

foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    7 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
    8 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 7],
    9 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 8],
    10 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 9],
] as $pageNumber => [$type, $parent]) {
    $putPointerMap($pages, $pageNumber, $type, $parent);
}

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 11, secureDelete: true);
$plan = SQLiteBTreePointerMapVacuumFreeblockCurrentSourceNextPlan::next127TableLeafFromDeleteResult(
    $database,
    3,
    [
        'page' => $deletedPage,
        'rowid' => 11,
        'obsolete_overflow_page_numbers' => [7, 8, 9, 10],
    ],
    4,
    true,
);

$summary = [
    'scenario' => 'application-btree-pointermap-vacuum-freeblock-current-source-next127',
    'application_use' => 'Delete a copied wp_options transient row, defragment its table-leaf freeblock, free obsolete overflow pages, and let incremental auto-vacuum truncate tail pages with pointer-map free-page evidence.',
    'released_overflow_pages' => $plan->releasedOverflowPages(),
    'truncated_released_overflow_pages' => $plan->truncatedReleasedOverflowPages(),
    'final_database_page_count' => $plan->toArray()['final_database_page_count'],
    'updated_page_numbers' => $plan->updatedPageNumbers(),
    'row_statuses' => array_column($plan->rows, 'vacuum_status'),
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    $ok = $summary['released_overflow_pages'] === [7, 8, 9, 10]
        && $summary['truncated_released_overflow_pages'] === [7, 8, 9, 10]
        && $summary['final_database_page_count'] === 6
        && $summary['updated_page_numbers'] === [1, 2, 3];
    if (!$ok) {
        fwrite(STDERR, "application-btree-pointermap-vacuum-freeblock-current-source-next127 self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-btree-pointermap-vacuum-freeblock-current-source-next127 self-test passed\n");
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
