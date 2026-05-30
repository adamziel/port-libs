<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexLeafBalanceApplyPlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexInteriorPage;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteRecord;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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
$firstPage = substr_replace($firstPage, pack('N', 5), 28, 4);
$firstPage = substr_replace($firstPage, pack('N', 1), 56, 4);

$parent = SQLiteIndexInteriorPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_b', 12]), leftChildPage: 3),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_z', 80]), leftChildPage: 4),
], 5, $pageSize);
$leftLeaf = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['no', '_transient_old', 7])),
], $pageSize);
$rightLeaf = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_c', 13])),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_d', 14])),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_e', 15])),
], $pageSize);
$trailingLeaf = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'zz_plugin', 90])),
], $pageSize);

$database = SQLiteDatabase::fromBytes($firstPage . $parent . $leftLeaf . $rightLeaf . $trailingLeaf);
$plan = SQLiteBTreeIndexLeafBalanceApplyPlan::apply($database, 2, 3, 4, 0);
$pages = [];
for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
    $pages[] = $plan->pageImages[$pageNumber] ?? $database->page($pageNumber);
}
$postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));
$recordsFor = static function (int $pageNumber) use ($postDatabase): array {
    $page = $postDatabase->page($pageNumber);
    $header = SQLiteBTreePageHeader::parsePage($page, $postDatabase->header->pageSize, $pageNumber === 1 ? 100 : 0);

    return array_map(
        static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
        SQLiteIndexCell::parsePageCells($page, $header, $postDatabase->usablePageSize()),
    );
};

echo json_encode([
    'scenario' => 'application-index-leaf-balance-apply',
    'applicationUse' => 'Apply a copied wp_options autoload-index delete rebalance by moving separator records through sibling index leaves and rewriting the parent divider without requiring ext/sqlite.',
    'balance' => $plan->toArray(),
    'leftIndexRecordsAfter' => $recordsFor(3),
    'rightIndexRecordsAfter' => $recordsFor(4),
    'parentDividerRecordsAfter' => $recordsFor(2),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
