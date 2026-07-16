<?php

declare(strict_types=1);

foreach (glob(__DIR__ . '/../src/*.php') ?: [] as $sourceFile) {
    require_once $sourceFile;
}

use PortLibs\LibSqlite\SQLiteBTreeIndexDeleteRebalancePlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexInteriorPage;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteRecord;

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

$encode = static fn (array $values): string => SQLiteIndexCell::encode(SQLiteRecord::encode($values));
$leftLeaf = SQLiteIndexLeafPage::assemble([
    $encode(['no', '_transient_timeout_feed', 7]),
    $encode(['no', '_transient_timeout_keep', 8]),
], $pageSize);
$rightLeaf = SQLiteIndexLeafPage::assemble([
    $encode(['yes', 'active_plugins', 12]),
    $encode(['yes', 'blog_public', 13]),
    $encode(['yes', 'stylesheet', 14]),
], $pageSize);
$tailLeaf = SQLiteIndexLeafPage::assemble([
    $encode(['yes', 'template', 15]),
], $pageSize);
$parent = SQLiteIndexInteriorPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'about_plugin', 11]), leftChildPage: 3),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'zz_tail', 90]), leftChildPage: 4),
], 5, $pageSize);

$database = SQLiteDatabase::fromBytes($firstPage . $parent . $leftLeaf . $rightLeaf . $tailLeaf);
$plan = SQLiteBTreeIndexDeleteRebalancePlan::deleteFromLeftAndRebalanceRight(
    $database,
    2,
    3,
    4,
    0,
    ['no', '_transient_timeout_feed', 7],
);

$pages = [];
for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
    $pages[] = $plan->pageImages[$pageNumber] ?? $database->page($pageNumber);
}
$postDatabase = SQLiteDatabase::fromBytes(implode('', $pages));
$parentHeader = SQLiteBTreePageHeader::parsePage($postDatabase->page(2), $pageSize);
$parentCells = SQLiteIndexCell::parsePageCells($postDatabase->page(2), $parentHeader, $postDatabase->usablePageSize());

echo json_encode([
    'scenario' => 'application-index-delete-rebalance-current-next23',
    'summary' => $plan->toArray(),
    'parent_divider_after_delete' => $parentCells[0]->record()->values,
    'requires_ext_sqlite' => false,
], JSON_PRETTY_PRINT) . PHP_EOL;
