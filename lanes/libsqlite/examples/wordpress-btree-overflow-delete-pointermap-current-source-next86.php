<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeOverflowDeletePointerMapCurrentSourceNextPlan;
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
    $page = substr_replace($page, pack('N', 8), 28, 4);
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

$pages = array_fill(1, 8, str_repeat("\0", 512));
$pages[1] = $firstPage();
$pages[2] = str_repeat("\0", 512);

$transientA = SQLiteTableLeafCell::encodeWithOverflowPages(
    11,
    SQLiteRecord::encode([null, '_transient_feed_a86', str_repeat('feed-a:', 100)]),
    6,
    512,
);
$transientB = SQLiteTableLeafCell::encodeWithOverflowPages(
    12,
    SQLiteRecord::encode([null, '_transient_feed_b86', str_repeat('feed-b:', 75)]),
    8,
    512,
);
$pages[3] = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(10, SQLiteRecord::encode([null, 'autoload', 'yes'])),
    $transientA['cell'],
    $transientB['cell'],
]);
$pages[6] = $transientA['overflowPages'][0];
$pages[8] = $transientB['overflowPages'][0];

$putPointerMapEntry($pages, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
$putPointerMapEntry($pages, 6, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
$putPointerMapEntry($pages, 8, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);

$plan = SQLiteBTreeOverflowDeletePointerMapCurrentSourceNextPlan::sequentialTableLeafDeletes(
    SQLiteDatabase::fromBytes(implode('', $pages)),
    3,
    11,
    12,
    true,
);

echo json_encode([
    'scenario' => 'wordpress-btree-overflow-delete-pointermap-current-source-next86',
    'current_deleted_rowids' => $plan->deletePlan->current->deletedRowIds,
    'next_deleted_rowids' => $plan->deletePlan->next->deletedRowIds,
    'released_overflow_pages' => $plan->releasedOverflowPageNumbers(),
    'materialized_page_numbers' => $plan->materializedPageNumbers(),
    'pointer_map_transition_pages' => array_column($plan->pointerMapTransitions, 'page_number'),
    'after_next_pointer_map_types' => array_column($plan->pointerMapTransitions, 'after_next_type_name'),
    'next_freelist_count' => $plan->deletePlan->next->freePlan->freelistPageCount,
], JSON_PRETTY_PRINT) . PHP_EOL;
