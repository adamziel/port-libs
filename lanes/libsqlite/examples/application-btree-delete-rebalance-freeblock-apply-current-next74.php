<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeDeleteRebalanceFreeblockApplyPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
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
    SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_feed', str_repeat('x', 120)])),
    SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, '_site_transient_update_plugins', 'fresh'])),
]);
$pages[4] = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['autoload', 1])),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['_transient_feed', 2, str_repeat('i', 40)])),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['_site_transient_update_plugins', 3])),
]);
$pages[6] = str_repeat('o', 512);
$pages[7] = str_repeat('p', 512);

$putPointerMapEntry($pages, 3, SQLitePointerMapEntry::BTREE_PAGE, 4);
$putPointerMapEntry($pages, 4, SQLitePointerMapEntry::ROOT_PAGE, 0);
$putPointerMapEntry($pages, 6, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
$putPointerMapEntry($pages, 7, SQLitePointerMapEntry::OVERFLOW_PAGE, 6);

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$delete = [
    'page' => SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true),
    'rowid' => 2,
    'obsolete_overflow_page_numbers' => [6, 7],
];

$plan = SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::tableLeafFromDeleteResult($database, 3, $delete, true);
$summary = $plan->toArray();

echo json_encode([
    'scenario' => 'application-btree-delete-rebalance-freeblock-apply-current-next74',
    'deleted_option_rowids' => $summary['deleted_rowids'],
    'leaf_page' => $summary['leaf_page'],
    'freeblock_bytes_before' => $summary['freeblock_bytes_before'],
    'freeblock_bytes_after' => $summary['freeblock_bytes_after'],
    'freed_overflow_pages' => $summary['freed_pages'],
    'updated_pointer_map_pages' => $summary['updated_pointer_map_page_numbers'],
    'updated_page_numbers' => $summary['updated_page_numbers'],
], JSON_PRETTY_PRINT) . PHP_EOL;
