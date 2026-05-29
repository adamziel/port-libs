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

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber, int $pageSize): void {
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
$pageCount = 416;
$pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
$pages[1] = $makeFirstPage($pageSize, $pageCount);

foreach ([4 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 55 => [SQLitePointerMapEntry::BTREE_PAGE, 4], 56 => [SQLitePointerMapEntry::BTREE_PAGE, 4]] as $pageNumber => [$type, $parent]) {
    $putPointerMapEntry($pages, $pageNumber, $type, $parent, $pageSize);
}

foreach ([412, 413, 415, 416] as $index => $pageNumber) {
    $first = $index === 0 || $index === 2;
    $parent = $first ? ($index === 0 ? 55 : 56) : [412, 413, 415, 416][$index - 1];
    $putPointerMapEntry(
        $pages,
        $pageNumber,
        $first ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
        $parent,
        $pageSize,
    );
    $pages[$pageNumber] = pack('N', in_array($pageNumber, [412, 415], true) ? $pageNumber + 1 : 0)
        . str_repeat(chr(88 + $index), $pageSize - 4);
}

$plan = SQLiteOverflowVacuumTruncatePlan::fromDeleteResults(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    [
        [
            'source' => 'wp_options table overflow vacuum tail',
            'obsolete_overflow_page_numbers' => [412, 413],
            'rowids' => [9201],
        ],
        [
            'source' => 'wp_options option_name index overflow vacuum tail',
            'obsolete_overflow_page_numbers' => [415, 416],
            'record_values' => [['_transient_vacuum_rows', 9201]],
        ],
    ],
    5,
    true,
);

echo json_encode([
    'scenario' => 'copied wp_options overflow vacuum truncates through auto-vacuum pointer-map page',
    'released_overflow_pages' => $plan->releasedOverflowPages(),
    'truncated_page_numbers' => $plan->truncatedPageNumbers(),
    'final_page_count' => $plan->finalDatabasePageCount(),
    'overflow_vacuum_truncate_rows' => $plan->overflowVacuumTruncateRows(),
    'materialized_apply' => $plan->materializedApplySummary(),
    'materialized_sha1' => sha1($plan->materializedBytes()),
], JSON_PRETTY_PRINT) . PHP_EOL;
