<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreeOverflowVacuumFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$makeFirstPage = static function (int $pageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', 5), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$makeFragmentedLeaf = static function (): string {
    $page = "\r" . str_repeat("\0", 511);
    $page = substr_replace($page, pack('n', 200), 1, 2);
    $page = substr_replace($page, pack('n', 1), 3, 2);
    $page = substr_replace($page, pack('n', 190), 5, 2);
    $page[7] = chr(2);
    $page = substr_replace($page, pack('n', 222) . pack('n', 20), 200, 4);
    $page = substr_replace($page, 'aaaaaaaaaaaaaaaa', 204, 16);
    $page = substr_replace($page, 'xy', 220, 2);
    $page = substr_replace($page, pack('n', 0) . pack('n', 30), 222, 4);
    $page = substr_replace($page, 'bbbbbbbbbbbbbbbbbbbbbbbbbb', 226, 26);
    $page = substr_replace($page, pack('n', 450), 8, 2);
    $page = substr_replace($page, str_repeat('C', 40), 450, 40);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2) {
        return;
    }

    $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};

$pages = array_fill(1, 14, str_repeat("\0", 512));
$pages[1] = $makeFirstPage(14);
$pages[2] = str_repeat("\0", 512);
$pages[3] = $makeFragmentedLeaf();
$pages[5] = "\n" . str_repeat("\0", 511);
$pages[10] = substr(str_pad(str_repeat('live-option-row-next137', 24), 512, 'x'), 0, 512);

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
    $putPointerMapEntry($pages, $pageNumber, $type, $parent);
}

foreach ([6 => 7, 7 => 8, 8 => 0, 12 => 13, 13 => 14, 14 => 0] as $pageNumber => $nextPage) {
    $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(65 + $pageNumber), 508);
}

$plan = SQLiteBTreeOverflowVacuumFreeblockCurrentSourceNextPlan::fromDeleteResults(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    3,
    [[
        'source' => 'wp_options-table-leaf-freeblock-vacuum-next137',
        'leaf_page' => 3,
        'rowids' => [13701, 13702],
        'obsolete_overflow_page_numbers' => [6, 7, 8],
    ], [
        'source' => 'wp_options-autoload-index-freeblock-vacuum-next137',
        'leaf_page' => 3,
        'record_values' => [['autoload', 'yes', 13701]],
        'obsolete_overflow_page_numbers' => [12, 13, 14],
    ]],
    3,
    5,
    str_repeat('next137-wordpress-overflow-vacuum-freeblock-', 24),
);

$summary = [
    'scenario' => 'wordpress-btree-overflow-vacuum-freeblock-current-source-next137',
    'released_overflow_pages' => $plan->releasedOverflowPages(),
    'surviving_vacuum_free_pages' => $plan->survivingFreedPointerMapPages(),
    'truncated_vacuum_free_pages' => $plan->truncatedFreedPointerMapPages(),
    'replacement_overflow_pages' => $plan->allocatedOverflowPages(),
    'reused_surviving_freed_pages' => $plan->reusedSurvivingFreedPages(),
    'replacement_pointer_map_parents' => array_column($plan->rows, 'next_pointer_map_parent'),
    'final_freelist_pages' => $plan->databaseAfterAllocation->freelistPageNumbers(),
];

if (in_array('--self-test', $argv, true)) {
    foreach ([
        'released_overflow_pages' => [6, 7, 8, 12, 13, 14],
        'surviving_vacuum_free_pages' => [6, 7, 8],
        'truncated_vacuum_free_pages' => [12, 13, 14],
        'replacement_overflow_pages' => [7, 8, 6],
        'reused_surviving_freed_pages' => [6, 7, 8],
        'replacement_pointer_map_parents' => [8, 5, 7, null, null, null],
        'final_freelist_pages' => [],
    ] as $key => $expected) {
        if ($summary[$key] !== $expected) {
            throw new RuntimeException("Unexpected {$key}: " . json_encode($summary[$key]));
        }
    }

    echo "wordpress-btree-overflow-vacuum-freeblock-current-source-next137 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
