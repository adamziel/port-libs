<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBTreeFreelistPointerMapReuseCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteBTreeFreeblock.php';
require_once __DIR__ . '/../src/SQLiteBTreePageHeader.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteFreelistAllocationPlan.php';
require_once __DIR__ . '/../src/SQLiteFreelistFreePlan.php';
require_once __DIR__ . '/../src/SQLiteFreelistTraversalPlan.php';
require_once __DIR__ . '/../src/SQLiteFreelistTrunkPage.php';
require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLitePointerMapEntry.php';
require_once __DIR__ . '/../src/SQLiteRecord.php';
require_once __DIR__ . '/../src/SQLiteTableLeafCell.php';
require_once __DIR__ . '/../src/SQLiteTableLeafPage.php';
require_once __DIR__ . '/../src/SQLiteVarint.php';

use PortLibs\LibSqlite\SQLiteBTreeFreelistPointerMapReuseCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage = static function (): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', 132), 28, 4);
    $page = substr_replace($page, pack('N', 4), 32, 4);
    $page = substr_replace($page, pack('N', 121), 36, 4);
    $page = substr_replace($page, pack('N', 3), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    $stride = intdiv(512, 5) + 1;
    $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
    if ($pointerMapPage === $pageNumber) {
        return;
    }

    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage] ?? str_repeat("\0", 512),
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$pages = array_fill(1, 132, str_repeat("\0", 512));
$existingLeaves = range(5, 124);
$pages[1] = $makeFirstPage();
$pages[3] = SQLiteTableLeafPage::assemble([]);
$pages[4] = SQLiteFreelistTrunkPage::assemble(null, $existingLeaves, 512);
$pages[125] = SQLiteTableLeafPage::assemble([]);

$putPointerMapEntry($pages, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
$putPointerMapEntry($pages, 4, SQLitePointerMapEntry::FREE_PAGE, 0);
foreach ($existingLeaves as $leafPageNumber) {
    $putPointerMapEntry($pages, $leafPageNumber, SQLitePointerMapEntry::FREE_PAGE, 0);
}
$putPointerMapEntry($pages, 125, SQLitePointerMapEntry::BTREE_PAGE, 3);
foreach ([126, 127, 128, 129, 131, 132] as $pageNumber) {
    $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::BTREE_PAGE, 125);
}
$putPointerMapEntry($pages, 130, SQLitePointerMapEntry::BTREE_PAGE, 125);

$plan = SQLiteBTreeFreelistPointerMapReuseCurrentSourceNextPlan::fromFreedPages(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    [130],
    1,
    125,
    [
        130 => SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(12401, SQLiteRecord::encode([null, '_transient_promoted_trunk_reused_next124', 'current source reuse', 'no'])),
        ]),
    ],
);

echo json_encode([
    'scenario' => 'wordpress-btree-freelist-pointermap-reuse-current-source-next124',
    'wordpressUse' => 'When a copied wp_options cleanup frees a page while the freelist trunk is full, the page becomes the next freelist trunk and can be immediately reused with its auto-vacuum pointer-map entry rewritten.',
    'freedPages' => $plan->freePlan->freedPageNumbers,
    'promotedTrunks' => $plan->promotedTrunkPageNumbers(),
    'reusedPromotedTrunks' => $plan->reusedPromotedTrunkPageNumbers(),
    'reuseRows' => $plan->reuseRows,
    'finalFirstFreelistTrunkPage' => $plan->databaseAfterReuse->header->firstFreelistTrunkPage,
    'finalFreelistPageCount' => $plan->databaseAfterReuse->header->freelistPageCount,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
