<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowFreeblockCoalesceCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 6), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 4), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$pointerMapPage = str_repeat("\0", $pageSize);
foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    5 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
    6 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 5],
] as $pageNumber => [$type, $parentPageNumber]) {
    $pointerMapPage = substr_replace($pointerMapPage, chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
}

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

$database = SQLiteDatabase::fromBytes(
    $firstPage
    . $pointerMapPage
    . $leafPage
    . str_repeat("\0", $pageSize)
    . pack('N', 6) . str_repeat('O', $pageSize - 4)
    . pack('N', 0) . str_repeat('P', $pageSize - 4),
);

$plan = SQLiteBTreeOverflowFreeblockCoalesceCurrentSourceNextPlan::fromDatabaseDeleteResults(
    $database,
    3,
    [
        [
            'source' => 'copied-wp-options-transient-overflow-delete',
            'obsolete_overflow_page_numbers' => [5, 6],
            'rowids' => [901],
        ],
    ],
    true,
    true,
);

echo json_encode([
    'wordpressUse' => 'After deleting an overflow-backed copied wp_options transient, coalesce the leaf freeblock fragments and release obsolete overflow pages into the freelist in one materialized page-image update.',
    'overflowFreeblockCoalesceCurrentSource' => $plan->toArray(),
    'freelistAfterDelete' => $plan->database->freelistPageNumbers(),
    'pointerMapTypes' => [
        5 => $plan->database->pointerMapEntryForPage(5)->typeName(),
        6 => $plan->database->pointerMapEntryForPage(6)->typeName(),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
