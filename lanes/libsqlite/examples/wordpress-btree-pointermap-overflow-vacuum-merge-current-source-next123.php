<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteHeader.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLitePointerMapEntry.php';
require_once __DIR__ . '/../src/SQLiteFreelistTrunkPage.php';
require_once __DIR__ . '/../src/SQLiteFreelistTraversalPlan.php';
require_once __DIR__ . '/../src/SQLiteFreelistFreePlan.php';
require_once __DIR__ . '/../src/SQLiteFreelistTruncatePlan.php';
require_once __DIR__ . '/../src/SQLiteOverflowFreelistReleasePlan.php';
require_once __DIR__ . '/../src/SQLiteOverflowVacuumTruncatePlan.php';
require_once __DIR__ . '/../src/SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage = static function (int $pageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
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

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2) {
        return;
    }

    $pages[2] = substr_replace(
        $pages[2],
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - 3),
        5,
    );
};

$pages = array_fill(1, 12, str_repeat("\0", 512));
$pages[1] = $makeFirstPage(12);
$pages[2] = str_repeat("\0", 512);
$pages[3] = "\r" . str_repeat("\0", 511);
$pages[4] = "\n" . str_repeat("\0", 511);
$pages[10] = substr(str_pad(str_repeat('wp-options-live-tail', 31), 512, 'x'), 0, 512);

foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    4 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    6 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
    7 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 6],
    10 => [SQLitePointerMapEntry::BTREE_PAGE, 4],
    11 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4],
    12 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 11],
] as $pageNumber => [$type, $parent]) {
    $putPointerMapEntry($pages, $pageNumber, $type, $parent);
}

foreach ([6 => 7, 7 => 0, 11 => 12, 12 => 0] as $pageNumber => $nextPage) {
    $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(65 + $pageNumber), 508);
}

$plan = SQLiteBTreePointerMapOverflowVacuumMergeCurrentSourceNextPlan::fromDeleteResults(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    [
        [
            'source' => 'wp_options-autoload-table-delete',
            'leaf_page' => 3,
            'rowids' => [12301],
            'obsolete_overflow_page_numbers' => [6, 7],
        ],
        [
            'source' => 'wp_options-option-name-index-merge',
            'leaf_page' => 4,
            'record_values' => [['_transient_next123', 12301]],
            'obsolete_overflow_page_numbers' => [11, 12],
        ],
    ],
    4,
    true,
);

echo json_encode([
    'action' => $plan->toArray()['action'],
    'released_overflow_pages' => $plan->releasedOverflowPages(),
    'surviving_freelist_pages' => $plan->survivingFreelistPages(),
    'truncated_freelist_pages' => $plan->truncatedFreelistPages(),
    'merge_statuses' => array_column($plan->pointerMapOverflowVacuumMergeRows(), 'merge_status'),
    'freelist_roles' => array_column($plan->pointerMapOverflowVacuumMergeRows(), 'freelist_role'),
    'final_page_count' => $plan->toArray()['final_database_page_count'],
    'final_freelist_page_count' => $plan->toArray()['final_freelist_page_count'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
