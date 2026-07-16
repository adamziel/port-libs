<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeDeleteRebalanceFreeblockApplyPlan;
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
    $pages[2] = substr_replace($pages[2], chr($type) . pack('N', $parentPageNumber), 5 * ($pageNumber - 3), 5);
};

$pages = array_fill(1, 7, str_repeat("\0", 512));
$pages[1] = $firstPage();
$pages[2] = str_repeat("\0", 512);
$pages[3] = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(21, SQLiteRecord::encode([null, '_transient_cache_alpha', 'old-a', 'no'])),
    SQLiteTableLeafCell::encode(22, SQLiteRecord::encode([null, '_transient_cache_beta', str_repeat('b', 120), 'no'])),
    SQLiteTableLeafCell::encode(23, SQLiteRecord::encode([null, '_transient_cache_gamma', 'keep-g', 'yes'])),
]);
$pages[6] = str_repeat('u', 512);
$pages[7] = str_repeat('v', 512);

$putPointerMapEntry($pages, 3, SQLitePointerMapEntry::BTREE_PAGE, 4);
$putPointerMapEntry($pages, 6, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
$putPointerMapEntry($pages, 7, SQLitePointerMapEntry::OVERFLOW_PAGE, 6);

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$delete = [
    'page' => SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 22, secureDelete: true),
    'rowid' => 22,
    'obsolete_overflow_page_numbers' => [6, 7],
];
$accepted = SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::tableLeafFromDeleteResult($database, 3, $delete, true)->toArray();

$staleDelete = [
    'page' => str_replace('keep-g', 'bad-g!', $delete['page']),
    'rowid' => 22,
    'obsolete_overflow_page_numbers' => [6, 7],
];

try {
    SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::tableLeafFromDeleteResult($database, 3, $staleDelete, true);
    $staleStatus = 'not rejected';
} catch (InvalidArgumentException $exception) {
    $staleStatus = $exception->getMessage();
}

echo json_encode([
    'scenario' => 'application-btree-write-apply-corruption-current-source-next106',
    'accepted_deleted_option_rowids' => $accepted['deleted_rowids'],
    'accepted_freed_overflow_pages' => $accepted['freed_pages'],
    'accepted_updated_pages' => $accepted['updated_page_numbers'],
    'stale_delete_result_status' => $staleStatus,
    'applicationUse' => 'Copied wp_options transient cleanup rejects stale post-delete leaf images before applying rebalance/freeblock writes, preventing old repair previews from overwriting newer option rows.',
], JSON_PRETTY_PRINT) . PHP_EOL;
