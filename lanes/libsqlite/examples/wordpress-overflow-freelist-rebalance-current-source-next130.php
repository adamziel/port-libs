<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreeOverflowFreelistRebalanceCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$firstPage = str_repeat("\0", 512);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', 512), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 10), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 8), 32, 4);
$firstPage = substr_replace($firstPage, pack('N', 3), 36, 4);
$firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$pages = array_fill(1, 10, str_repeat("\0", 512));
$pages[1] = $firstPage;
$pages[3] = SQLiteTableLeafPage::assemble([]);
$pages[4] = SQLiteTableLeafPage::assemble([]);
$pages[5] = SQLiteTableLeafPage::assemble([]);
$pages[6] = str_pad(pack('N', 7) . str_repeat('O', 508), 512, "\0");
$pages[7] = str_pad(pack('N', 0) . str_repeat('P', 220), 512, "\0");
$pages[8] = SQLiteFreelistTrunkPage::assemble(null, [9, 10], 512);

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    $pointerMapPage = (intdiv($pageNumber - 2, 103) * 103) + 2;
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

foreach ([3 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 4 => [SQLitePointerMapEntry::BTREE_PAGE, 3], 5 => [SQLitePointerMapEntry::BTREE_PAGE, 3], 8 => [SQLitePointerMapEntry::FREE_PAGE, 0], 9 => [SQLitePointerMapEntry::FREE_PAGE, 0], 10 => [SQLitePointerMapEntry::FREE_PAGE, 0]] as $pageNumber => [$type, $parent]) {
    $putPointerMapEntry($pages, $pageNumber, $type, $parent);
}
$putPointerMapEntry($pages, 6, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
$putPointerMapEntry($pages, 7, SQLitePointerMapEntry::OVERFLOW_PAGE, 6);

$plan = SQLiteBTreeOverflowFreelistRebalanceCurrentSourceNextPlan::fromDeleteResults(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    [[
        'source' => 'wp_options transient shrink after cache cleanup',
        'obsolete_overflow_page_numbers' => [6, 7],
        'rowids' => [13001],
    ]],
    3,
    str_repeat('current-source-next130:', 22),
    true,
);

echo json_encode([
    'wordpressUse' => 'Shrink a copied wp_options transient value whose obsolete overflow pages must stay safely on the freelist because older free pages satisfy the smaller replacement overflow chain first.',
    'releasedOverflowPages' => $plan->releasedOverflowPages(),
    'allocatedOverflowPages' => $plan->allocatedOverflowPages(),
    'deferredReleasedOverflowPages' => $plan->deferredReleasedOverflowPages(),
    'reusedExistingFreelistPages' => $plan->reusedExistingFreelistPages(),
    'finalFreelistPages' => $plan->databaseAfterAllocation->freelistPageNumbers(),
    'releasedPointerMapTypes' => array_column($plan->releasedRows(), 'final_pointer_map_type'),
    'allocatedPointerMapTypes' => array_column($plan->allocatedRows(), 'final_pointer_map_type'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
