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
    $page = substr_replace($page, pack('N', 7), 28, 4);
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

$pages = array_fill(1, 7, str_repeat("\0", 512));
$pages[1] = $firstPage();
$pages[2] = str_repeat("\0", 512);
$pages[3] = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(44, SQLiteRecord::encode([null, '_transient_timeout_update_plugins', str_repeat('x', 112)])),
]);
$pages[4] = str_repeat('r', 512);
$pages[5] = str_repeat('s', 512);
$pages[6] = str_repeat('t', 512);
$pages[7] = str_repeat('u', 512);

$putPointerMapEntry($pages, 3, SQLitePointerMapEntry::BTREE_PAGE, 4);
$putPointerMapEntry($pages, 4, SQLitePointerMapEntry::ROOT_PAGE, 0);
$putPointerMapEntry($pages, 5, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
$putPointerMapEntry($pages, 6, SQLitePointerMapEntry::OVERFLOW_PAGE, 5);
$putPointerMapEntry($pages, 7, SQLitePointerMapEntry::OVERFLOW_PAGE, 6);

$plan = SQLiteBTreeOverflowRebalanceFreepageCurrentSourceNextPlan::next94TableLeaf(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    3,
    [
        ['rowid' => 44, 'obsolete_overflow_page_numbers' => [5, 6, 7]],
    ],
    true,
);

$summary = [
    'scenario' => 'wordpress-btree-overflow-rebalance-freepage-current-source-next94',
    'wordpressUse' => 'Copied wp_options transient cleanup can free an emptied leaf page and its obsolete overflow chain into the same current-source freelist, including auto-vacuum pointer-map free-page entries, without ext/sqlite.',
    'action' => $plan->toArray()['action'],
    'step_type' => $plan->events[0]['step_type'],
    'freed_pages' => $plan->events[0]['freed_pages'],
    'materialized_page_numbers' => $plan->materializedPageNumbers(),
    'final_freelist_page_count' => $plan->finalFreelistPageCount(),
    'leaf_pointer_map_type' => $plan->databaseAfter->pointerMapEntryForPage(3)->typeName(),
    'overflow_pointer_map_type' => $plan->databaseAfter->pointerMapEntryForPage(7)->typeName(),
];

if (
    $summary['step_type'] !== 'empty-leaf-free'
    || $summary['freed_pages'] !== [3, 5, 6, 7]
    || $summary['final_freelist_page_count'] !== 4
    || $summary['leaf_pointer_map_type'] !== 'free-page'
    || $summary['overflow_pointer_map_type'] !== 'free-page'
) {
    fwrite(STDERR, "wordpress-btree-overflow-rebalance-freepage-current-source-next94 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
