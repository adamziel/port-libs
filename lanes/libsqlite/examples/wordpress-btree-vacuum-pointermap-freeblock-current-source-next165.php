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
require_once __DIR__ . '/../src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
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
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
    SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next165', str_repeat('cache:', 42)])),
    SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
]);
$pages[105] = str_repeat("\0", 512);
foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
    $pages[$pageNumber] = pack('N', $nextPage) . str_repeat('P', 508);
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
$plan = SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext165(
    $database,
    3,
    [
        'page' => $deletedPage,
        'rowid' => 2,
        'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
    ],
    2,
    str_repeat('next165-multisite-transient-rewrite-', 42),
    3,
    true,
);

$summary = [
    'scenario' => 'wordpress-btree-vacuum-pointermap-freeblock-current-source-next165',
    'wordpressUse' => 'Delete a copied wp_options transient, vacuum tail overflow pages, reuse surviving free pages for a replacement overflow chain, and compare current-source page bytes with the next image before admitting writes.',
    'changed_writable_pages' => $plan->changedWritablePages(),
    'unchanged_writable_pages' => $plan->unchangedWritablePages(),
    'rejected_current_source_pages' => $plan->rejectedCurrentSourcePages(),
    'replacement_overflow_pages' => $plan->toArray()['replacement_overflow_pages'],
    'replacement_overflow_next_pages' => $plan->toArray()['replacement_overflow_next_pages'],
    'replacement_pointer_map_parents' => $plan->toArray()['replacement_pointer_map_parents'],
];

if (($argv[1] ?? null) === '--self-test') {
    $expected = [
        'changed_writable_pages' => [1, 3, 105, 106, 107, 108],
        'unchanged_writable_pages' => [],
        'rejected_current_source_pages' => [109, 110],
        'replacement_overflow_pages' => [107, 108, 106],
        'replacement_overflow_next_pages' => [108, 106, 0],
        'replacement_pointer_map_parents' => [3, 107, 108],
    ];
    foreach ($expected as $key => $value) {
        if ($summary[$key] !== $value) {
            fwrite(STDERR, "wordpress-btree-vacuum-pointermap-freeblock-current-source-next165 self-test failed\n");
            exit(1);
        }
    }

    fwrite(STDOUT, "wordpress-btree-vacuum-pointermap-freeblock-current-source-next165 self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
