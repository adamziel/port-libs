<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$pageCount = 207;
$pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));

$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', $pageCount), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 5), 32, 4);
$firstPage = substr_replace($firstPage, pack('N', 6), 36, 4);
$firstPage = substr_replace($firstPage, pack('N', 4), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);
$pages[1] = $firstPage;
$pages[5] = SQLiteFreelistTrunkPage::assemble(106, [3, 104], $pageSize);
$pages[106] = SQLiteFreelistTrunkPage::assemble(null, [107, 206], $pageSize);

$putPointerMapEntry = static function (int $pageNumber, int $type, int $parentPageNumber) use (&$pages, $pageSize): void {
    $stride = intdiv($pageSize, 5) + 1;
    $pointerMapPage = (intdiv($pageNumber - 2, $stride) * $stride) + 2;
    if ($pointerMapPage === $pageNumber) {
        return;
    }

    $offset = 5 * ($pageNumber - $pointerMapPage - 1);
    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage],
        chr($type) . pack('N', $parentPageNumber),
        $offset,
        5,
    );
};

foreach ([5, 106, 3, 104, 107, 206] as $pageNumber) {
    $putPointerMapEntry($pageNumber, SQLitePointerMapEntry::FREE_PAGE, 0);
}
$putPointerMapEntry(4, SQLitePointerMapEntry::ROOT_PAGE, 0);
$putPointerMapEntry(11, SQLitePointerMapEntry::BTREE_PAGE, 4);

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$plan = $database->planBtreePageAllocation(6, 11, false);

echo json_encode([
    'applicationUse' => 'Allocate copied wp_options b-tree pages from the current and next freelist trunks while rewriting auto-vacuum pointer-map entries for every newly-current page without ext/sqlite.',
    'allocatedPages' => $plan->allocatedPageNumbers,
    'appendedPages' => $plan->appendedPageNumbers,
    'updatedPointerMapPages' => array_keys($plan->updatedPointerMapPages),
    'allocatedPointerMapEntries' => $plan->allocatedPointerMapEntries(),
    'freelistAfter' => [
        'first_trunk_page' => $plan->firstFreelistTrunkPage,
        'page_count' => $plan->freelistPageCount,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
