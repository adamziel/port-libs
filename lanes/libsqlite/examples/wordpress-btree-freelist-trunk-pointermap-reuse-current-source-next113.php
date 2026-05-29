<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBTreeFreelistTrunkPointerMapReuseCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteBTreeFreeblock.php';
require_once __DIR__ . '/../src/SQLiteBTreePageHeader.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteFreelistAllocationPlan.php';
require_once __DIR__ . '/../src/SQLiteFreelistTraversalPlan.php';
require_once __DIR__ . '/../src/SQLiteFreelistTrunkPage.php';
require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteIndexLeafPage.php';
require_once __DIR__ . '/../src/SQLitePointerMapEntry.php';
require_once __DIR__ . '/../src/SQLiteRecord.php';
require_once __DIR__ . '/../src/SQLiteTableLeafCell.php';
require_once __DIR__ . '/../src/SQLiteTableLeafPage.php';
require_once __DIR__ . '/../src/SQLiteVarint.php';

use PortLibs\LibSqlite\SQLiteBTreeFreelistTrunkPointerMapReuseCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
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
    $page = substr_replace($page, pack('N', 112), 28, 4);
    $page = substr_replace($page, pack('N', 4), 32, 4);
    $page = substr_replace($page, pack('N', 4), 36, 4);
    $page = substr_replace($page, pack('N', 42), 52, 4);
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

$pages = array_fill(1, 112, str_repeat("\0", 512));
$pages[1] = $makeFirstPage();
$pages[4] = SQLiteFreelistTrunkPage::assemble(106, [5], 512);
$pages[106] = SQLiteFreelistTrunkPage::assemble(null, [107], 512);

$putPointerMapEntry($pages, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
$putPointerMapEntry($pages, 4, SQLitePointerMapEntry::FREE_PAGE, 0);
$putPointerMapEntry($pages, 5, SQLitePointerMapEntry::FREE_PAGE, 0);
$putPointerMapEntry($pages, 42, SQLitePointerMapEntry::BTREE_PAGE, 3);
$putPointerMapEntry($pages, 106, SQLitePointerMapEntry::FREE_PAGE, 0);
$putPointerMapEntry($pages, 107, SQLitePointerMapEntry::FREE_PAGE, 0);

$plan = SQLiteBTreeFreelistTrunkPointerMapReuseCurrentSourceNextPlan::fromDatabase(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    2,
    42,
    [
        5 => SQLiteTableLeafPage::assemble([
            SQLiteTableLeafCell::encode(11301, SQLiteRecord::encode([null, '_transient_reused_leaf_next113', 'leaf', 'no'])),
        ]),
        4 => SQLiteIndexLeafPage::assemble([
            SQLiteRecord::encode(['_transient_reused_trunk_next113', 11301]),
        ]),
    ],
);

echo json_encode([
    'scenario' => 'wordpress-btree-freelist-trunk-pointermap-reuse-current-source-next113',
    'wordpressUse' => 'After copied wp_options cleanup consumes the last leaf from a freelist trunk, the trunk itself is reused as a B-tree page while the next trunk remains the freelist head.',
    'allocatedPages' => $plan->allocatedPageNumbers(),
    'trunkReuseRows' => $plan->trunkPointerMapReuseRows(),
    'finalFirstFreelistTrunkPage' => $plan->databaseAfterReuse->header->firstFreelistTrunkPage,
    'finalFreelistPages' => $plan->databaseAfterReuse->freelistPageNumbers(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
