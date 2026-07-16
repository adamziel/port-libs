<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan;
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
$firstPage = substr_replace($firstPage, pack('N', 14), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 5), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$fragmentedLeaf = "\r" . str_repeat("\0", 511);
$fragmentedLeaf = substr_replace($fragmentedLeaf, pack('n', 200), 1, 2);
$fragmentedLeaf = substr_replace($fragmentedLeaf, pack('n', 1), 3, 2);
$fragmentedLeaf = substr_replace($fragmentedLeaf, pack('n', 190), 5, 2);
$fragmentedLeaf[7] = chr(2);
$fragmentedLeaf = substr_replace($fragmentedLeaf, pack('n', 222) . pack('n', 20), 200, 4);
$fragmentedLeaf = substr_replace($fragmentedLeaf, 'aaaaaaaaaaaaaaaa', 204, 16);
$fragmentedLeaf = substr_replace($fragmentedLeaf, 'xy', 220, 2);
$fragmentedLeaf = substr_replace($fragmentedLeaf, pack('n', 0) . pack('n', 30), 222, 4);
$fragmentedLeaf = substr_replace($fragmentedLeaf, 'bbbbbbbbbbbbbbbbbbbbbbbbbb', 226, 26);
$fragmentedLeaf = substr_replace($fragmentedLeaf, pack('n', 450), 8, 2);
$fragmentedLeaf = substr_replace($fragmentedLeaf, str_repeat('C', 40), 450, 40);

$pages = array_fill(1, 14, str_repeat("\0", $pageSize));
$pages[1] = $firstPage;
$pages[2] = str_repeat("\0", $pageSize);
$pages[3] = $fragmentedLeaf;
$pages[5] = "\n" . str_repeat("\0", 511);
$pages[10] = substr(str_pad(str_repeat('live-option-row-next122', 24), 512, 'x'), 0, 512);

$putPointerMapEntry = static function (int $pageNumber, int $type, int $parentPageNumber) use (&$pages): void {
    if ($pageNumber === 2) {
        return;
    }

    $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};

foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    5 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    6 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
    7 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 6],
    8 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 7],
    10 => [SQLitePointerMapEntry::BTREE_PAGE, 5],
    12 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
    13 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 12],
    14 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 13],
] as $pageNumber => [$type, $parent]) {
    $putPointerMapEntry($pageNumber, $type, $parent);
}

foreach ([6 => 7, 7 => 8, 8 => 0, 12 => 13, 13 => 14, 14 => 0] as $pageNumber => $nextPage) {
    $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(65 + $pageNumber), 508);
}

$plan = SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNextPlan::coalescedOverflowFreeblockFromDeleteResults(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    3,
    [
        [
            'source' => 'wp_options-table-leaf-defrag-delete',
            'leaf_page' => 3,
            'rowids' => [12201, 12202],
            'obsolete_overflow_page_numbers' => [6, 7, 8],
        ],
        [
            'source' => 'wp_options-autoload-index-delete',
            'leaf_page' => 3,
            'record_values' => [['autoload', 'yes', 12201]],
            'obsolete_overflow_page_numbers' => [12, 13, 14],
        ],
    ],
    3,
    true,
);

echo json_encode([
    'applicationUse' => 'Copied wp_options cleanup coalesces fragmented table-leaf freeblocks before releasing obsolete overflow pages, then incremental-vacuums only tail overflow pages while preserving a live page.',
    'scenario' => 'application-btree-overflow-freeblock-vacuum-current-source-next122',
    'coalescedFragmentBytes' => $plan->coalescePlan->coalescedFragmentBytes,
    'releasedOverflowPages' => $plan->releasedOverflowPages(),
    'survivingFreedPointerMapPages' => $plan->survivingFreedPointerMapPages(),
    'truncatedFreedPointerMapPages' => $plan->truncatedFreedPointerMapPages(),
    'rows' => $plan->overflowFreeblockVacuumRows(),
    'materializedApply' => $plan->materializedApplySummary(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
