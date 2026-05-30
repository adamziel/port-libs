<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteHeader.php';
require __DIR__ . '/../src/SQLiteDatabase.php';
require __DIR__ . '/../src/SQLiteBTreePageHeader.php';
require __DIR__ . '/../src/SQLiteBTreeFreeblock.php';
require __DIR__ . '/../src/SQLiteBTreeLeafPageCompactor.php';
require __DIR__ . '/../src/SQLitePointerMapEntry.php';
require __DIR__ . '/../src/SQLiteTableInteriorCell.php';
require __DIR__ . '/../src/SQLiteTableInteriorPage.php';
require __DIR__ . '/../src/SQLiteIndexCell.php';
require __DIR__ . '/../src/SQLiteIndexInteriorPage.php';
require __DIR__ . '/../src/SQLiteRecord.php';
require __DIR__ . '/../src/SQLiteBTreeInteriorRedistributionPlan.php';
require __DIR__ . '/../src/SQLiteBTreeInteriorRedistributionApplyPlan.php';
require __DIR__ . '/../src/SQLiteVarint.php';
require __DIR__ . '/../src/SQLiteBlobValue.php';

use PortLibs\LibSqlite\SQLiteBTreeInteriorRedistributionApplyPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteTableInteriorCell;
use PortLibs\LibSqlite\SQLiteTableInteriorPage;

$pageSize = 512;
$firstPage = static function (int $pageCount) use ($pageSize): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', 12), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber) use ($pageSize): void {
    $stride = intdiv($pageSize, 5) + 1;
    $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
    if ($pointerMapPage === $pageNumber) {
        return;
    }

    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage] ?? str_repeat("\0", $pageSize),
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$pages = array_fill(1, 18, str_repeat("\0", $pageSize));
$pages[1] = $firstPage(18);
$pages[3] = SQLiteTableInteriorPage::assemble([
    SQLiteTableInteriorCell::encode(7, 20),
    SQLiteTableInteriorCell::encode(8, 60),
], 9, $pageSize);
$pages[7] = SQLiteTableInteriorPage::assemble([
    SQLiteTableInteriorCell::encode(10, 10),
], 11, $pageSize);
$pages[8] = SQLiteTableInteriorPage::assemble([
    SQLiteTableInteriorCell::encode(12, 30),
    SQLiteTableInteriorCell::encode(13, 40),
    SQLiteTableInteriorCell::encode(14, 50),
], 15, $pageSize);
$pages[9] = SQLiteTableInteriorPage::assemble([
    SQLiteTableInteriorCell::encode(16, 70),
], 17, $pageSize);

foreach ([3 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 7 => [SQLitePointerMapEntry::BTREE_PAGE, 3], 8 => [SQLitePointerMapEntry::BTREE_PAGE, 3], 9 => [SQLitePointerMapEntry::BTREE_PAGE, 3]] as $pageNumber => [$type, $parent]) {
    $putPointerMapEntry($pages, $pageNumber, $type, $parent);
}
foreach ([10 => 7, 11 => 7, 12 => 8, 13 => 8, 14 => 8, 15 => 8, 16 => 9, 17 => 9] as $pageNumber => $parent) {
    $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::BTREE_PAGE, $parent);
}

$plan = SQLiteBTreeInteriorRedistributionApplyPlan::tableCurrentAndNext(SQLiteDatabase::fromBytes(implode('', $pages)), 3, 7);

echo json_encode([
    'scenario' => 'copied wp_options autoload index current/next interior redistribution current-next72',
    'summary' => 'Current underfull interior page 7 borrows from next sibling page 8, rewrites the parent divider, and retargets moved child pointer-map ownership before a copied Application options repair continues.',
    'action' => $plan->toArray()['action'],
    'current_page' => $plan->redistributionPlan->leftPageNumber,
    'next_page' => $plan->redistributionPlan->rightPageNumber,
    'parent_page' => $plan->redistributionPlan->parentPageNumber,
    'old_divider' => $plan->parentDividerUpdate['old_separator'],
    'new_divider' => $plan->parentDividerUpdate['new_separator'],
    'moved_child_page_numbers' => $plan->toArray()['moved_child_page_numbers'],
    'updated_page_numbers' => $plan->updatedPageNumbers(),
    'updated_pointer_map_page_numbers' => $plan->toArray()['updated_pointer_map_page_numbers'],
    'pointer_map_entries' => $plan->pointerMapEntries,
    'dependency_closure' => 'no new support component needed; this composes existing b-tree page assembly, pointer-map updates, and native SQLite database image helpers',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
