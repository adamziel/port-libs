<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan;
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
$firstPage = substr_replace($firstPage, pack('N', 12), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 4), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$pages = array_fill(1, 12, str_repeat("\0", $pageSize));
$pages[1] = $firstPage;
$pages[2] = str_repeat("\0", $pageSize);
$pages[3] = "\r" . str_repeat("\0", 511);
$pages[4] = "\n" . str_repeat("\0", 511);
$pages[10] = substr(str_pad(str_repeat('live-wp-option-page', 30), 512, 'x'), 0, 512);

$putPointerMapEntry = static function (int $pageNumber, int $type, int $parentPageNumber) use (&$pages): void {
    if ($pageNumber === 2) {
        return;
    }

    $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};

foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    4 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    6 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
    7 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 6],
    10 => [SQLitePointerMapEntry::BTREE_PAGE, 4],
    11 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4],
    12 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 11],
] as $pageNumber => [$type, $parent]) {
    $putPointerMapEntry($pageNumber, $type, $parent);
}

foreach ([6 => 7, 7 => 0, 11 => 12, 12 => 0] as $pageNumber => $nextPage) {
    $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(80 + $pageNumber), 508);
}

$plan = SQLiteBTreeDeleteOverflowVacuumPointerMapCurrentSourceNextPlan::fromDeleteResults(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    [
        [
            'source' => 'wp_options-table-delete',
            'leaf_page' => 3,
            'page' => $pages[3],
            'rowids' => [11901],
            'obsolete_overflow_page_numbers' => [6, 7],
        ],
        [
            'source' => 'wp_options-option-name-index-delete',
            'leaf_page' => 4,
            'page' => $pages[4],
            'record_values' => [['_transient_next119', 11901]],
            'obsolete_overflow_page_numbers' => [11, 12],
        ],
    ],
    2,
    true,
);

echo json_encode([
    'applicationUse' => 'Copied wp_options transient cleanup deletes table and option_name index overflow chains, frees their pointer-map entries, and incremental-vacuums only the tail pages while preserving a live page before the tail.',
    'scenario' => 'application-btree-delete-overflow-vacuum-pointermap-current-source-next119',
    'releasedOverflowPages' => $plan->releasedOverflowPages(),
    'survivingFreedPointerMapPages' => $plan->survivingFreedPointerMapPages(),
    'truncatedFreedPointerMapPages' => $plan->truncatedFreedPointerMapPages(),
    'rows' => $plan->deleteOverflowVacuumPointerMapRows(),
    'materializedApply' => $plan->materializedApplySummary(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
