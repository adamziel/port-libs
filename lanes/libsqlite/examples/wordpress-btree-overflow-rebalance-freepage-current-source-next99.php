<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowRebalanceFreepageCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstPage = static function (): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', 9), 28, 4);
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

$pages = array_fill(1, 9, str_repeat("\0", 512));
$pages[1] = $firstPage();
$pages[2] = str_repeat("\0", 512);
$pages[3] = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(101, SQLiteRecord::encode([null, '_transient_timeout_update_core', str_repeat('a', 88)])),
    SQLiteTableLeafCell::encode(102, SQLiteRecord::encode([null, '_transient_update_core', str_repeat('b', 90)])),
]);
$pages[4] = str_repeat('r', 512);
$pages[5] = str_repeat('s', 512);
$pages[6] = str_repeat('t', 512);
$pages[7] = str_repeat('u', 512);
$pages[8] = str_repeat('v', 512);
$pages[9] = str_repeat('w', 512);

$putPointerMapEntry($pages, 3, SQLitePointerMapEntry::BTREE_PAGE, 4);
$putPointerMapEntry($pages, 4, SQLitePointerMapEntry::ROOT_PAGE, 0);
$putPointerMapEntry($pages, 5, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
$putPointerMapEntry($pages, 6, SQLitePointerMapEntry::OVERFLOW_PAGE, 5);
$putPointerMapEntry($pages, 7, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
$putPointerMapEntry($pages, 8, SQLitePointerMapEntry::OVERFLOW_PAGE, 7);
$putPointerMapEntry($pages, 9, SQLitePointerMapEntry::OVERFLOW_PAGE, 8);

$plan = SQLiteBTreeOverflowRebalanceFreepageCurrentSourceNextPlan::next99TableLeaf(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    3,
    [
        ['rowid' => 101, 'obsolete_overflow_page_numbers' => [5, 6]],
        ['rowid' => 102, 'obsolete_overflow_page_numbers' => [7, 8, 9]],
    ],
    true,
);

$summary = [
    'scenario' => 'wordpress-btree-overflow-rebalance-freepage-current-source-next99',
    'wordpressUse' => 'Copied wp_options transient cleanup deletes two overflow-backed rows from the current source: the first delete defragments the still-live leaf, while the second frees the emptied leaf and its overflow chain into the same auto-vacuum freelist without ext/sqlite.',
    'action' => $plan->toArray()['action'],
    'transition_step_types' => array_column($plan->transitionRows(), 'step_type'),
    'transition_freed_pages' => array_column($plan->transitionRows(), 'freed_pages'),
    'freelist_pages' => $plan->databaseAfter()->freelistPageNumbers(),
    'materialized_page_numbers' => $plan->materializedPageNumbers(),
    'final_freelist_page_count' => $plan->finalFreelistPageCount(),
    'leaf_pointer_map_type' => $plan->databaseAfter()->pointerMapEntryForPage(3)->typeName(),
    'tail_pointer_map_parent' => $plan->databaseAfter()->pointerMapEntryForPage(9)->parentPageNumber,
];

if (
    $summary['transition_step_types'] !== ['freeblock-rebalance', 'empty-leaf-free']
    || $summary['transition_freed_pages'] !== [[5, 6], [3, 7, 8, 9]]
    || $summary['final_freelist_page_count'] !== 6
    || $summary['leaf_pointer_map_type'] !== 'free-page'
    || $summary['tail_pointer_map_parent'] !== 0
) {
    fwrite(STDERR, "wordpress-btree-overflow-rebalance-freepage-current-source-next99 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
