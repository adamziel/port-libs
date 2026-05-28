<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOverflowVacuumTruncatePlan;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$pageSize = 512;
$pageCount = 310;
$releasedPages = [306, 307, 308, 309, 310];
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
$pages[1] = substr_replace($pages[1], pack('N', 0), 32, 4);
$pages[1] = substr_replace($pages[1], pack('N', 0), 36, 4);
$pages[1] = substr_replace($pages[1], pack('N', 4), 52, 4);
$pages[1] = substr_replace($pages[1], pack('N', 1), 56, 4);

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

$putPointerMapEntry($pages, 4, SQLitePointerMapEntry::ROOT_PAGE, 0);
$putPointerMapEntry($pages, 42, SQLitePointerMapEntry::BTREE_PAGE, 4);
foreach ($releasedPages as $index => $pageNumber) {
    $isFirst = $index === 0 || $index === 3;
    $parent = $isFirst ? ($index === 0 ? 42 : 4) : $releasedPages[$index - 1];
    $putPointerMapEntry(
        $pages,
        $pageNumber,
        $isFirst ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
        $parent,
    );
    $pages[$pageNumber] = pack('N', in_array($pageNumber, [306, 307, 309], true) ? $pageNumber + 1 : 0)
        . str_repeat(chr(65 + $index), $pageSize - 4);
}

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$plan = SQLiteOverflowVacuumTruncatePlan::fromDeleteResults(
    $database,
    [
        [
            'source' => 'wp_options-autoload-table-tail-overflow',
            'obsolete_overflow_page_numbers' => [306, 307, 308],
            'rowids' => [9001],
        ],
        [
            'source' => 'wp_options-option-name-index-tail-overflow',
            'obsolete_overflow_page_numbers' => [309, 310],
            'record_values' => [['_transient_doing_cron', 9001]],
        ],
    ],
    16,
    true,
);

echo json_encode([
    'action' => $plan->toArray()['action'],
    'current_page_count' => $plan->currentDatabase->pageCount(),
    'current_freelist_pages' => $plan->currentFreelistPageNumbers(),
    'freed_pointer_map_pages' => array_column($plan->currentFreedPointerMapEntries(), 'page_number'),
    'truncated_pages' => $plan->truncatedPageNumbers(),
    'next_page_count' => $plan->nextDatabase->pageCount(),
    'next_freelist_pages' => $plan->nextFreelistPageNumbers(),
], JSON_PRETTY_PRINT) . PHP_EOL;
