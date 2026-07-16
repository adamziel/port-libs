<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreeDeleteRebalanceFreeblockApplyPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$firstPage = static function (int $pageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
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
$pages[1] = $firstPage(8);
$pages[2] = str_repeat("\0", 512);
$pages[3] = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(201, SQLiteRecord::encode([null, 'active_plugins', 'a:1:{}'])),
    SQLiteTableLeafCell::encode(202, SQLiteRecord::encode([null, '_transient_timeout_update_plugins', str_repeat('x', 130)])),
    SQLiteTableLeafCell::encode(203, SQLiteRecord::encode([null, 'rewrite_rules', 'fresh'])),
]);
$pages[6] = str_repeat('o', 512);
$pages[7] = str_repeat('p', 512);
$pages[8] = str_repeat('q', 512);

$putPointerMapEntry($pages, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
$putPointerMapEntry($pages, 6, SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3);
$putPointerMapEntry($pages, 7, SQLitePointerMapEntry::OVERFLOW_PAGE, 6);
$putPointerMapEntry($pages, 8, SQLitePointerMapEntry::OVERFLOW_PAGE, 7);

$database = SQLiteDatabase::fromBytes(implode('', $pages));
$deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 202, secureDelete: true);
$plan = SQLiteBTreeDeleteRebalanceFreeblockApplyPlan::tableLeafFromDeleteResult(
    $database,
    3,
    [
        'page' => $deletedPage,
        'rowid' => 202,
        'obsolete_overflow_page_numbers' => [6, 7, 8],
    ],
    true,
);

echo 'Application transient delete source hash: ' . substr($plan->currentSourcePageHash, 0, 12) . PHP_EOL;
echo 'Application transient delete next hash: ' . substr($plan->nextLeafPageHash, 0, 12) . PHP_EOL;
echo 'Application transient delete freed overflow pages: ' . implode(',', $plan->obsoleteOverflowPageNumbers) . PHP_EOL;
echo 'Application transient delete write order: ' . implode(',', $plan->writeOrderPageNumbers) . PHP_EOL;
echo 'Application transient delete freeblock delta: ' . $plan->freeblockBytesDelta . PHP_EOL;
