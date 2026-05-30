<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeDeleteOverflowPlan;
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
    $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};

$pages = array_fill(1, 8, str_repeat("\0", 512));
$pages[1] = $firstPage();
$pages[2] = str_repeat("\0", 512);
$pages[3] = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'autoload', 'yes'])),
    SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_feed_a', str_repeat('a', 130)])),
    SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, '_transient_feed_b', str_repeat('b', 128)])),
    SQLiteTableLeafCell::encode(4, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
]);
$pages[6] = str_repeat('a', 512);
$pages[7] = str_repeat('b', 512);
$pages[8] = str_repeat('c', 512);

$putPointerMapEntry($pages, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
$putPointerMapEntry($pages, 6, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
$putPointerMapEntry($pages, 7, SQLitePointerMapEntry::OVERFLOW_PAGE, 6);
$putPointerMapEntry($pages, 8, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$currentDelete = [
    'page' => SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true),
    'rowid' => 2,
    'obsolete_overflow_page_numbers' => [6, 7],
];

$plan = SQLiteBTreeDeleteOverflowPlan::sequentialTableLeafDeletes(
    $database,
    3,
    $currentDelete,
    3,
    [8],
    true,
);

echo json_encode([
    'scenario' => 'wordpress-btree-delete-overflow',
    'current_deleted_rowids' => $plan->current->deletedRowIds,
    'next_deleted_rowids' => $plan->next->deletedRowIds,
    'released_overflow_pages' => $plan->releasedOverflowPageNumbers(),
    'current_freelist_count' => $plan->current->freePlan->freelistPageCount,
    'next_freelist_count' => $plan->next->freePlan->freelistPageCount,
    'materialized_page_numbers' => $plan->materializedPageNumbers(),
    'remaining_leaf_cell_count' => $plan->next->cellCountAfter,
], JSON_PRETTY_PRINT) . PHP_EOL;
