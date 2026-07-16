<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOverflowVacuumTruncatePlan;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$pageCount = 260;
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
$firstPage = substr_replace($firstPage, pack('N', 4), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);
$pages[1] = $firstPage;

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

foreach ([257, 258, 259, 260] as $index => $pageNumber) {
    $putPointerMapEntry(
        $pages,
        $pageNumber,
        $index === 0 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE,
        $index === 0 ? 11 : $pageNumber - 1,
    );
    $pages[$pageNumber] = str_repeat(chr(65 + $index), $pageSize);
}

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$plan = SQLiteOverflowVacuumTruncatePlan::fromDeleteResults($database, [
    [
        'source' => 'wp_options transient table overflow',
        'obsolete_overflow_page_numbers' => [257, 258],
        'rowids' => [410],
    ],
    [
        'source' => 'wp_options option_name index overflow',
        'obsolete_overflow_page_numbers' => [259, 260],
        'record_values' => [['_transient_tail_cleanup', 410]],
    ],
], 8, true);

echo json_encode([
    'scenario' => 'wp_options overflow release followed by current incremental vacuum tail truncation',
    'released_overflow_pages' => $plan->releasedOverflowPages(),
    'truncated_page_numbers' => $plan->truncatedPageNumbers(),
    'final_database_page_count' => $plan->finalDatabasePageCount(),
    'final_freelist_page_count' => $plan->finalFreelistPageCount(),
    'updated_page_numbers' => array_keys($plan->pageImages),
], JSON_PRETTY_PRINT) . PHP_EOL;
