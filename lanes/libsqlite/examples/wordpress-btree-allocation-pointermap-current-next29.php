<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityCheck;

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
$firstPage = substr_replace($firstPage, pack('N', 8), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 4), 32, 4);
$firstPage = substr_replace($firstPage, pack('N', 4), 36, 4);
$firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$pages = array_fill(1, 8, str_repeat("\0", $pageSize));
$pages[1] = $firstPage;
$pages[4] = SQLiteFreelistTrunkPage::assemble(null, [6, 7, 8], $pageSize);
$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber) use ($pageSize): void {
    $offset = 5 * ($pageNumber - 3);
    $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parentPageNumber), $offset, 5);
};
$leaf = str_repeat("\0", $pageSize);
$leaf[0] = "\x0d";
$leaf = substr_replace($leaf, pack('n', 0), 3, 2);
$leaf = substr_replace($leaf, pack('n', $pageSize), 5, 2);
$pages[3] = $leaf;
$pages[5] = $leaf;
foreach ([4, 6, 7, 8] as $pageNumber) {
    $putPointerMapEntry($pages, $pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0);
}
$putPointerMapEntry($pages, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
$putPointerMapEntry($pages, 5, SQLitePointerMapEntry::BTREE_PAGE, 3);

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$plan = $database->planBtreePageAllocation(2, 3, false);
$post = $database->applyPageAllocationPlan($plan, [6 => $leaf, 8 => $leaf]);

echo json_encode([
    'scenario' => 'wp_options allocation reuses freelist leaves and rewrites auto-vacuum pointer-map entries',
    'allocated_pages' => $plan->allocatedPageNumbers,
    'remaining_freelist' => $post->freelistPageNumbers(),
    'allocated_pointer_map_entries' => $plan->allocatedPointerMapEntries(),
    'integrity' => SQLitePragmaIntegrityCheck::execute('PRAGMA integrity_check', $post)['rows'],
], JSON_PRETTY_PRINT) . "\n";
