<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;

$firstPage = str_repeat("\0", 512);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', 512), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 13), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 10), 32, 4);
$firstPage = substr_replace($firstPage, pack('N', 4), 36, 4);
$firstPage = substr_replace($firstPage, pack('N', 4), 52, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$leafPage = str_repeat("\xdd", 512);
$leafPage[0] = "\x0d";
$leafPage = substr_replace($leafPage, pack('n', 390), 1, 2);
$leafPage = substr_replace($leafPage, pack('n', 1), 3, 2);
$leafPage = substr_replace($leafPage, pack('n', 376), 5, 2);
$leafPage[7] = chr(7);
$leafPage = substr_replace($leafPage, pack('n', 496), 8, 2);
$leafPage = substr_replace($leafPage, str_repeat('L', 12), 496, 12);
$leafPage = substr_replace($leafPage, pack('n', 406) . pack('n', 12), 390, 4);
$leafPage = substr_replace($leafPage, pack('n', 422) . pack('n', 14), 406, 4);
$leafPage = substr_replace($leafPage, pack('n', 0) . pack('n', 18), 422, 4);

$pages = array_fill(1, 13, str_repeat("\0", 512));
$pages[1] = $firstPage;
$pages[2] = str_repeat("\0", 512);
$pages[3] = $leafPage;
$pages[5] = pack('N', 6) . str_repeat('T', 508);
$pages[6] = pack('N', 0) . str_repeat('U', 508);
$pages[8] = pack('N', 9) . str_repeat('I', 508);
$pages[9] = pack('N', 0) . str_repeat('J', 508);
$pages[10] = SQLiteFreelistTrunkPage::assemble(null, [11, 12, 13], 512);

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

foreach ([
    3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    5 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
    6 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 5],
    8 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 4],
    9 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 8],
    10 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    11 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    12 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    13 => [SQLitePointerMapEntry::FREE_PAGE, 0],
] as $pageNumber => [$type, $parent]) {
    $putPointerMapEntry($pages, $pageNumber, $type, $parent);
}

$plan = SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan::tableAndIndexReleasedOverflowReuseFromCurrentSourceDeleteResults(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    3,
    [
        [
            'source' => 'wp_options-autoload-released-overflow-reuse',
            'first_page' => 5,
            'overflow_payload_bytes' => 1016,
        ],
        [
            'source' => 'wp_options-option-name-index-released-overflow-reuse',
            'first_page' => 8,
            'overflow_payload_bytes' => 1016,
        ],
    ],
    [
        [
            'source' => 'wp_options-autoload-released-overflow-reuse',
            'rowid' => 15301,
            'obsolete_overflow_page_numbers' => [5, 6],
        ],
        [
            'source' => 'wp_options-option-name-index-released-overflow-reuse',
            'record_values' => [['_transient_reuse', 15301]],
            'obsolete_overflow_page_numbers' => [8, 9],
        ],
    ],
    3,
    str_repeat('N', 2540),
    true,
);

$summary = [
    'scenario' => 'application-btree-vacuum-pointermap-freeblock-released-overflow-reuse',
    'application_use' => 'Delete copied wp_options table and index overflow chains, materialize the leaf freeblock repair, then reuse released overflow pages with fresh auto-vacuum pointer-map parents before a later incremental vacuum.',
    'released_overflow_pages' => $plan->toArray()['released_overflow_pages'],
    'allocated_overflow_pages' => $plan->toArray()['allocated_overflow_pages'],
    'reused_released_overflow_pages' => $plan->toArray()['reused_released_overflow_pages'],
    'reuse_statuses' => array_column($plan->rows, 'vacuum_reuse_status'),
    'next_pointer_map_parents' => array_column($plan->reusedReleasedRows(), 'next_pointer_map_parent'),
    'next_overflow_next_pages' => array_column($plan->reusedReleasedRows(), 'next_overflow_next_page'),
    'final_freelist_page_numbers' => $plan->toArray()['final_freelist_page_numbers'],
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $summary['released_overflow_pages'] !== [5, 6, 8, 9]
        || $summary['allocated_overflow_pages'] !== [11, 9, 8, 6, 5]
        || $summary['reused_released_overflow_pages'] !== [5, 6, 8, 9]
        || $summary['next_pointer_map_parents'] !== [11, 9, 8, 6]
        || $summary['final_freelist_page_numbers'] !== [10, 13, 12]
    ) {
        fwrite(STDERR, "application-btree-vacuum-pointermap-freeblock-released-overflow-reuse self-test failed\n");
        exit(1);
    }

    fwrite(STDOUT, "application-btree-vacuum-pointermap-freeblock-released-overflow-reuse self-test passed\n");
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
