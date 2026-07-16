<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreePointerMapVacuumOverflowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

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
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    $pointerMapPage = $pageNumber < 105 ? 2 : 105;
    if ($pageNumber === $pointerMapPage) {
        return;
    }

    $offset = 5 * ($pageNumber - $pointerMapPage - 1);
    $pages[$pointerMapPage] = substr_replace($pages[$pointerMapPage], chr($type) . pack('N', $parentPageNumber), $offset, 5);
};

$pages = array_fill(1, 106, str_repeat("\0", 512));
$pages[1] = $makeFirstPage(106);
$pages[2] = str_repeat("\0", 512);
$pages[3] = str_repeat("\0", 512);
$pages[3][0] = "\x0d";
$pages[104] = pack('N', 106) . str_repeat('D', 508);
$pages[105] = str_repeat("\0", 512);
$pages[106] = pack('N', 0) . str_repeat('E', 508);

$putPointerMapEntry($pages, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
$putPointerMapEntry($pages, 104, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
$putPointerMapEntry($pages, 106, SQLitePointerMapEntry::OVERFLOW_PAGE, 104);

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$plan = SQLiteBTreePointerMapVacuumOverflowCurrentSourceNextPlan::fromDeleteResults(
    $database,
    [[
        'source' => 'wp_options-autoload-transient-current-source-next133',
        'leaf_page' => 3,
        'obsolete_overflow_page_numbers' => [104, 106],
        'rowids' => [13301],
    ]],
    3,
    3,
    str_repeat('N', 600),
);

echo 'Application btree133 released overflow pages: ' . implode(',', $plan->releasedOverflowPages()) . "\n";
echo 'Application btree133 vacuum truncated pages: ' . implode(',', $plan->truncatedPageNumbers()) . "\n";
echo 'Application btree133 recreated pointer-map pages: ' . implode(',', $plan->recreatedPointerMapPages()) . "\n";
echo 'Application btree133 allocated overflow pages: ' . implode(',', $plan->allocatedOverflowPages()) . "\n";
echo 'Application btree133 next pointer-map types: ' . implode(',', array_column($plan->rows, 'next_pointer_map_type')) . "\n";
echo 'Application btree133 final page count: ' . $plan->databaseAfterAllocation->pageCount() . "\n";
