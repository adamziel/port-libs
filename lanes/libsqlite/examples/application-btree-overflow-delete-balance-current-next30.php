<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteBTreeTableDeleteRebalancePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableInteriorCell;
use PortLibs\LibSqlite\SQLiteTableInteriorPage;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$pageSize = 512;
$firstPage = str_repeat("\0", $pageSize);
$firstPage = substr_replace($firstPage, "SQLite format 3\0", 0, 16);
$firstPage = substr_replace($firstPage, pack('n', $pageSize), 16, 2);
$firstPage[18] = "\x01";
$firstPage[19] = "\x01";
$firstPage[20] = "\x00";
$firstPage[21] = "\x40";
$firstPage[22] = "\x20";
$firstPage[23] = "\x20";
$firstPage = substr_replace($firstPage, pack('N', 6), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$record = static fn (string $name, string $value, string $autoload): string => SQLiteRecord::encode([null, $name, $value, $autoload]);
$stalePayload = $record('_transient_feed_cache', str_repeat('stale-feed:', 80), 'no');
$stale = SQLiteTableLeafCell::encodeWithOverflowPages(10, $stalePayload, 6, $pageSize);
$leftLeaf = SQLiteTableLeafPage::assemble([
    $stale['cell'],
    SQLiteTableLeafCell::encode(20, $record('_transient_feed_timeout', '1700000000', 'no'), $pageSize),
], $pageSize);
$rightLeaf = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(30, $record('active_plugins', 'a:0:{}', 'yes'), $pageSize),
    SQLiteTableLeafCell::encode(40, $record('blog_public', '1', 'yes'), $pageSize),
    SQLiteTableLeafCell::encode(50, $record('stylesheet', 'twentytwentyfour', 'yes'), $pageSize),
], $pageSize);
$tailLeaf = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(9000, $record('template', 'twentytwentyfour', 'yes'), $pageSize),
], $pageSize);
$parent = SQLiteTableInteriorPage::assemble([
    SQLiteTableInteriorCell::encode(3, 20),
    SQLiteTableInteriorCell::encode(4, 8999),
], 5, $pageSize);
$overflowPages = array_combine(range(6, 5 + count($stale['overflowPages'])), $stale['overflowPages']);

$database = SQLiteDatabase::fromBytes($firstPage . $parent . $leftLeaf . $rightLeaf . $tailLeaf . implode('', $overflowPages));
$plan = SQLiteBTreeTableDeleteRebalancePlan::deleteFromLeftAndRebalanceRight(
    $database,
    2,
    3,
    4,
    0,
    10,
    static function (int $firstOverflowPage, int $byteCount) use ($overflowPages): array {
        $pages = [];
        $pageNumber = $firstOverflowPage;
        $remaining = $byteCount;
        while ($pageNumber !== 0 && $remaining > 0) {
            $page = $overflowPages[$pageNumber] ?? null;
            if ($page === null) {
                throw new InvalidArgumentException('Missing fixture overflow page');
            }
            $pages[] = $pageNumber;
            $pageNumber = unpack('N', substr($page, 0, 4))[1];
            $remaining -= min($remaining, 508);
        }

        return $pages;
    },
);

$pages = [];
for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
    $pages[] = $plan->pageImages[$pageNumber] ?? $database->page($pageNumber);
}
$postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));
$leftHeader = SQLiteBTreePageHeader::parsePage($postDatabase->page(3), $pageSize);
$rightHeader = SQLiteBTreePageHeader::parsePage($postDatabase->page(4), $pageSize);

echo json_encode([
    'scenario' => 'application-btree-overflow-delete-balance-current-next30',
    'summary' => $plan->toArray(),
    'remainingCurrentRowids' => array_map(
        static fn (SQLiteTableLeafCell $cell): int => $cell->rowId,
        SQLiteTableLeafCell::parsePageCells($postDatabase->page(3), $leftHeader),
    ),
    'remainingNextRowids' => array_map(
        static fn (SQLiteTableLeafCell $cell): int => $cell->rowId,
        SQLiteTableLeafCell::parsePageCells($postDatabase->page(4), $rightHeader),
    ),
    'requires_ext_sqlite' => false,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
