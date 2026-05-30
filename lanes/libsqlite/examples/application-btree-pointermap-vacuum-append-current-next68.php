<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreeOverflowAutoVacuumPointerMapPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOverflowVacuumTruncatePlan;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage = static function (int $pageSize, int $pageCount): string {
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
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber, int $pageSize = 512): void {
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

$pageSize = 512;
$pageCount = 212;
$releasedPages = [209, 210, 211, 212];
$pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
$pages[1] = $makeFirstPage($pageSize, $pageCount);
$putPointerMapEntry($pages, 4, SQLitePointerMapEntry::ROOT_PAGE, 0, $pageSize);
$putPointerMapEntry($pages, 42, SQLitePointerMapEntry::BTREE_PAGE, 4, $pageSize);

foreach ($releasedPages as $index => $pageNumber) {
    $parent = $index === 0 ? 42 : $releasedPages[$index - 1];
    $putPointerMapEntry(
        $pages,
        $pageNumber,
        $index === 0 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
        $parent,
        $pageSize,
    );
    $pages[$pageNumber] = pack('N', $index < count($releasedPages) - 1 ? $releasedPages[$index + 1] : 0) . str_repeat('w', $pageSize - 4);
}

$vacuum = SQLiteOverflowVacuumTruncatePlan::fromDeleteResults(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    [[
        'source' => 'wp_options transient overflow cleanup before next autoload insert',
        'obsolete_overflow_page_numbers' => $releasedPages,
        'rowids' => [441],
    ]],
    8,
    true,
);

$append = SQLiteBTreeOverflowAutoVacuumPointerMapPlan::allocateCurrentNextChain(
    $vacuum->materializedDatabase(),
    42,
    str_repeat('n', 1100),
    true,
);

echo json_encode([
    'scenario' => 'copied wp_options pointer-map vacuum followed by appended overflow insert current/next68',
    'vacuum_final_page_count' => $vacuum->finalDatabasePageCount(),
    'vacuum_truncated_pages' => $vacuum->truncatedPageNumbers(),
    'allocated_overflow_pages' => $append->allocationPlan->allocatedPageNumbers,
    'appended_overflow_pages' => $append->allocationPlan->appendedPageNumbers,
    'recreated_pointer_map_pages' => $append->updatedPointerMapPageNumbers(),
    'pointer_map_entries' => $append->pointerMapEntries,
    'chain_links' => $append->chainLinks,
    'post_vacuum_insert_page_count' => $append->database->pageCount(),
], JSON_PRETTY_PRINT) . PHP_EOL;
