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
$pageCount = 412;
$releasedPages = [406, 407, 408, 409, 410, 411, 412];
$pages = array_fill(1, $pageCount, str_repeat("\0", $pageSize));
$pages[1] = $makeFirstPage($pageSize, $pageCount);

foreach ([4 => [SQLitePointerMapEntry::ROOT_PAGE, 0], 44 => [SQLitePointerMapEntry::BTREE_PAGE, 4], 45 => [SQLitePointerMapEntry::BTREE_PAGE, 4]] as $pageNumber => [$type, $parent]) {
    $putPointerMapEntry($pages, $pageNumber, $type, $parent, $pageSize);
}

foreach ($releasedPages as $index => $pageNumber) {
    $first = $index === 0 || $index === 3;
    $parent = $first ? ($index === 0 ? 44 : 45) : $releasedPages[$index - 1];
    $putPointerMapEntry(
        $pages,
        $pageNumber,
        $first ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
        $parent,
        $pageSize,
    );
    $next = in_array($pageNumber, [406, 407, 409, 410, 411], true) ? $pageNumber + 1 : 0;
    $pages[$pageNumber] = pack('N', $next) . str_repeat(chr(80 + $index), $pageSize - 4);
}

$plan = SQLiteOverflowVacuumTruncatePlan::fromDeleteResults(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    [
        [
            'source' => 'wp_options table overflow freeblock delete',
            'obsolete_overflow_page_numbers' => [406, 407, 408],
            'rowids' => [701, 702],
        ],
        [
            'source' => 'wp_options option_name index overflow freeblock delete',
            'obsolete_overflow_page_numbers' => [409, 410, 411, 412],
            'record_values' => [['_transient_timeout_overflow_rows', 701]],
        ],
    ],
    4,
    true,
);

echo json_encode([
    'scenario' => 'copied wp_options overflow/freeblock release with incremental vacuum truncate',
    'released_overflow_pages' => $plan->releasedOverflowPages(),
    'final_page_count' => $plan->finalDatabasePageCount(),
    'overflow_freeblock_truncate_rows' => $plan->overflowFreeblockTruncateRows(),
    'materialized_apply' => $plan->materializedApplySummary(),
    'materialized_sha1' => sha1($plan->materializedBytes()),
], JSON_PRETTY_PRINT) . PHP_EOL;
