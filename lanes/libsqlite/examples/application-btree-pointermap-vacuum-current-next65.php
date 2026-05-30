<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$pageSize = 512;
$pageCount = 106;
$pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));

$pages[1] = str_repeat("\0", $pageSize);
$pages[1] = substr_replace($pages[1], "SQLite format 3\0", 0, 16);
$pages[1] = substr_replace($pages[1], pack('n', $pageSize), 16, 2);
$pages[1][18] = "\x01";
$pages[1][19] = "\x01";
$pages[1][20] = "\x00";
$pages[1][21] = "\x40";
$pages[1][22] = "\x20";
$pages[1][23] = "\x20";
$pages[1] = substr_replace($pages[1], pack('N', $pageCount), 28, 4);
$pages[1] = substr_replace($pages[1], pack('N', 104), 32, 4);
$pages[1] = substr_replace($pages[1], pack('N', 2), 36, 4);
$pages[1] = substr_replace($pages[1], pack('N', 4), 52, 4);
$pages[1] = substr_replace($pages[1], pack('N', 1), 56, 4);
$pages[104] = SQLiteFreelistTrunkPage::assemble(null, [106], $pageSize);
$pages[106] = str_repeat('Z', $pageSize);

$putPointerMapEntry = static function (int $pageNumber, int $type, int $parentPageNumber) use (&$pages, $pageSize): void {
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

$putPointerMapEntry(4, SQLitePointerMapEntry::ROOT_PAGE, 0);
$putPointerMapEntry(42, SQLitePointerMapEntry::BTREE_PAGE, 4);
$putPointerMapEntry(104, SQLitePointerMapEntry::FREE_PAGE, 0);
$putPointerMapEntry(106, SQLitePointerMapEntry::FREE_PAGE, 0);

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$plan = $database->planFreelistTailTruncation(8);

echo json_encode([
    'action' => 'application-btree-pointermap-vacuum-current-next65',
    'current_page_count' => $database->pageCount(),
    'current_pointer_map_tail_page' => 105,
    'truncated_pages' => $plan->truncatedPageNumbers,
    'next_page_count' => $plan->databasePageCount,
    'next_first_freelist_trunk_page' => $plan->firstFreelistTrunkPage,
    'next_freelist_page_count' => $plan->freelistPageCount,
    'updated_page_numbers' => array_keys($plan->pageImages()),
], JSON_PRETTY_PRINT) . PHP_EOL;
