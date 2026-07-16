<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeInteriorRedistributionCurrentNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexInteriorPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 16), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 12), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$payload = static fn (string $autoload, string $name, int $rowid): string => SQLiteRecord::encode([$autoload, $name, $rowid]);
$divider = $payload('no', '_transient_timeout_feed', 20);
$pages = array_fill(1, 16, str_repeat("\0", $pageSize));
$pages[1] = $firstPage;
$pages[3] = SQLiteIndexInteriorPage::assemble([
    SQLiteIndexCell::encode($divider, $pageSize, null, 7),
], 8, $pageSize);
$pages[7] = SQLiteIndexInteriorPage::assemble([
    SQLiteIndexCell::encode($payload('no', '_transient_feed', 10), $pageSize, null, 10),
], 11, $pageSize);
$pages[8] = SQLiteIndexInteriorPage::assemble([
    SQLiteIndexCell::encode($payload('yes', 'blogname', 30), $pageSize, null, 12),
    SQLiteIndexCell::encode($payload('yes', 'siteurl', 40), $pageSize, null, 13),
    SQLiteIndexCell::encode($payload('yes', 'users_can_register', 50), $pageSize, null, 14),
], 15, $pageSize);

$putPointerMapEntry = static function (int $pageNumber, int $type, int $parentPageNumber) use (&$pages, $pageSize): void {
    $stride = intdiv($pageSize, 5) + 1;
    $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
    if ($pointerMapPage === $pageNumber) {
        return;
    }

    $offset = 5 * ($pageNumber - $pointerMapPage - 1);
    $pages[$pointerMapPage] = substr_replace($pages[$pointerMapPage], chr($type) . pack('N', $parentPageNumber), $offset, 5);
};
foreach ([3 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 7 => [SQLitePointerMapEntry::BTREE_PAGE, 3], 8 => [SQLitePointerMapEntry::BTREE_PAGE, 3]] as $pageNumber => [$type, $parent]) {
    $putPointerMapEntry($pageNumber, $type, $parent);
}
foreach ([10 => 7, 11 => 7, 12 => 8, 13 => 8, 14 => 8, 15 => 8] as $pageNumber => $parent) {
    $putPointerMapEntry($pageNumber, SQLitePointerMapEntry::BTREE_PAGE, $parent);
}

$plan = SQLiteBTreeInteriorRedistributionCurrentNextPlan::indexInterior(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    7,
    8,
    3,
    $divider,
);

echo json_encode([
    'applicationUse' => 'Apply a copied wp_options option_name index interior sibling redistribution from the current database image to the next image, including parent divider replacement and auto-vacuum pointer-map ownership changes.',
    'updatedPageNumbers' => $plan->updatedPageNumbers(),
    'parentDividerUpdate' => $plan->toArray()['parent_divider_update'],
    'movedChildPageNumbers' => $plan->toArray()['moved_child_page_numbers'],
    'pointerMapTransitions' => $plan->pointerMapTransitions,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
