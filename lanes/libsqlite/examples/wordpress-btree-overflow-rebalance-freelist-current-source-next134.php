<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreeOverflowRebalanceFreelistCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteFreelistTrunkPage;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage = static function (int $pageCount, int $firstFreelistTrunkPage, int $freelistPageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $firstFreelistTrunkPage), 32, 4);
    $page = substr_replace($page, pack('N', $freelistPageCount), 36, 4);
    $page = substr_replace($page, pack('N', 4), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2) {
        return;
    }

    $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};

$pages = array_fill(1, 12, str_repeat("\0", 512));
$pages[1] = $makeFirstPage(12, 10, 2);
$pages[2] = str_repeat("\0", 512);
$firstPayload = SQLiteRecord::encode([null, '_transient_timeout_update_core', str_repeat('a', 1077)]);
$secondPayload = SQLiteRecord::encode([null, '_site_transient_update_plugins', 'still-local']);
$first = SQLiteTableLeafCell::encodeWithOverflowPages(13401, $firstPayload, 6, 512);
$pages[3] = SQLiteTableLeafPage::assemble([
    $first['cell'],
    SQLiteTableLeafCell::encode(13402, $secondPayload),
]);
foreach ($first['overflowPages'] as $offset => $overflowPage) {
    $pages[6 + $offset] = $overflowPage;
}
$pages[10] = SQLiteFreelistTrunkPage::assemble(null, [12], 512);

foreach ([
    3 => [SQLitePointerMapEntry::BTREE_PAGE, 4],
    4 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
    6 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
    7 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 6],
    10 => [SQLitePointerMapEntry::FREE_PAGE, 0],
    12 => [SQLitePointerMapEntry::FREE_PAGE, 0],
] as $pageNumber => [$type, $parent]) {
    $putPointerMapEntry($pages, $pageNumber, $type, $parent);
}

$plan = SQLiteBTreeOverflowRebalanceFreelistCurrentSourceNextPlan::tableLeafReplacement(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    3,
    [13401],
    str_repeat('wordpress-option-replacement-next134-', 35),
    4,
    true,
);

$summary = [
    'scenario' => 'wordpress-btree-overflow-rebalance-freelist-current-source-next134',
    'deleted_overflow_pages' => $plan->releasedOverflowPages(),
    'replacement_overflow_pages' => $plan->allocatedOverflowPages(),
    'reused_deleted_overflow_pages' => $plan->reusedReleasedOverflowPages(),
    'replacement_pointer_map_parents' => array_column($plan->reuseRows(), 'next_pointer_map_parent'),
    'final_freelist_pages' => $plan->databaseAfterAllocation->freelistPageNumbers(),
];

if (in_array('--self-test', $argv, true)) {
    foreach ([
        'deleted_overflow_pages' => [6, 7],
        'replacement_overflow_pages' => [12, 7, 6],
        'reused_deleted_overflow_pages' => [7, 6],
        'replacement_pointer_map_parents' => [4, 12, 7],
        'final_freelist_pages' => [10],
    ] as $key => $expected) {
        if ($summary[$key] !== $expected) {
            throw new RuntimeException("Unexpected {$key}: " . json_encode($summary[$key]));
        }
    }

    echo "wordpress-btree-overflow-rebalance-freelist-current-source-next134 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
