<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 6), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 3), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$leafPage = str_repeat("\xcc", $pageSize);
$leafPage[0] = "\x0d";
$leafPage = substr_replace($leafPage, pack('n', 400), 1, 2);
$leafPage = substr_replace($leafPage, pack('n', 1), 3, 2);
$leafPage = substr_replace($leafPage, pack('n', 384), 5, 2);
$leafPage[7] = chr(6);
$leafPage = substr_replace($leafPage, pack('n', 500), 8, 2);
$leafPage = substr_replace($leafPage, str_repeat('W', 8), 500, 8);
$leafPage = substr_replace($leafPage, pack('n', 413) . pack('n', 12), 400, 4);
$leafPage = substr_replace($leafPage, pack('n', 428) . pack('n', 12), 413, 4);
$leafPage = substr_replace($leafPage, pack('n', 0) . pack('n', 16), 428, 4);

$pages = [
    1 => $firstPage,
    2 => str_repeat("\0", $pageSize),
    3 => $leafPage,
    4 => str_repeat("\0", $pageSize),
    5 => pack('N', 6) . str_repeat('O', 508),
    6 => pack('N', 0) . str_repeat('P', 508),
];

foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    5 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
    6 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 5],
] as $pageNumber => [$type, $parent]) {
    $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
}

$plan = SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceNextPlan::baseFromDeleteResults(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    3,
    [[
        'source' => 'copied-wp-options-transient-overflow-replace',
        'obsolete_overflow_page_numbers' => [5, 6],
        'rowids' => [12801],
    ]],
    3,
    str_repeat('replacement-wp-option-next128-', 28),
    true,
);

$output = [
    'wordpressUse' => 'A copied wp_options import deletes an overflow-backed transient, coalesces the current leaf freeblocks, then immediately reuses the freed overflow pages for the replacement value with auto-vacuum pointer-map entries rewritten from obsolete overflow to free-page to current/next overflow ownership.',
    'btreeOverflowFreeblockPointerMapCurrentSourceNext128' => $plan->toArray(),
    'freelistAfterReplacement' => $plan->databaseAfterAllocation->freelistPageNumbers(),
    'pointerMapTypes' => [
        6 => $plan->databaseAfterAllocation->pointerMapEntryForPage(6)->typeName(),
        5 => $plan->databaseAfterAllocation->pointerMapEntryForPage(5)->typeName(),
    ],
];

if ($output['freelistAfterReplacement'] !== [] || $output['pointerMapTypes'][6] !== 'first-overflow-page') {
    fwrite(STDERR, "unexpected overflow freeblock pointer-map transition\n");
    exit(1);
}

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
