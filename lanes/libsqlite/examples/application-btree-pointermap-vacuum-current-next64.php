<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

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
$pageCount = 310;
$releasedPages = [306, 307, 308, 309, 310];
$pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
$pages[1] = $makeFirstPage($pageSize, $pageCount);
$putPointerMapEntry($pages, 4, SQLitePointerMapEntry::ROOT_PAGE, 0, $pageSize);
$putPointerMapEntry($pages, 42, SQLitePointerMapEntry::BTREE_PAGE, 4, $pageSize);
foreach ($releasedPages as $index => $pageNumber) {
    $first = $index === 0 || $index === 3;
    $parent = $first ? ($index === 0 ? 42 : 4) : $releasedPages[$index - 1];
    $putPointerMapEntry(
        $pages,
        $pageNumber,
        $first ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
        $parent,
        $pageSize,
    );
    $pages[$pageNumber] = pack('N', in_array($pageNumber, [306, 307, 309], true) ? $pageNumber + 1 : 0) . str_repeat('w', $pageSize - 4);
}

$plan = SQLiteOverflowVacuumTruncatePlan::fromDeleteResults(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    [
        [
            'source' => 'wp_options autoload payload delete',
            'obsolete_overflow_page_numbers' => [306, 307, 308],
            'rowids' => [9001],
        ],
        [
            'source' => 'wp_options option_name index delete',
            'obsolete_overflow_page_numbers' => [309, 310],
            'record_values' => [['_transient_doing_cron', 9001]],
        ],
    ],
    3,
    true,
);

echo json_encode([
    'scenario' => 'copied wp_options overflow delete plus incremental vacuum pointer-map current/next64',
    'released_overflow_pages' => $plan->releasedOverflowPages(),
    'surviving_freed_pointer_map_pages' => $plan->survivingFreedPointerMapPages(),
    'truncated_freed_pointer_map_pages' => $plan->truncatedFreedPointerMapPages(),
    'final_database_page_count' => $plan->finalDatabasePageCount(),
    'pointer_map_vacuum_transitions' => $plan->pointerMapVacuumTransitions(),
], JSON_PRETTY_PRINT) . PHP_EOL;
