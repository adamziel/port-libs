<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeLeafMergePlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;

$leftTablePage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(41, SQLiteRecord::encode([null, 'autoload_a', 'a:1:{}', 'yes'])),
    SQLiteTableLeafCell::encode(42, SQLiteRecord::encode([null, 'autoload_b', 'a:1:{}', 'yes'])),
], $pageSize);
$rightTablePage = SQLiteTableLeafPage::assemble([
    SQLiteTableLeafCell::encode(43, SQLiteRecord::encode([null, 'autoload_c', 'a:1:{}', 'yes'])),
    SQLiteTableLeafCell::encode(44, SQLiteRecord::encode([null, 'autoload_d', 'a:1:{}', 'yes'])),
], $pageSize);

$leftIndexPage = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['no', '_transient_cache', 40])),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_a', 41])),
], $pageSize);
$rightIndexPage = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_b', 42])),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_c', 43])),
], $pageSize);

$tablePlan = SQLiteBTreeLeafMergePlan::tableLeaf($leftTablePage, $rightTablePage, 8, 9, 2, $pageSize);
$indexPlan = SQLiteBTreeLeafMergePlan::indexLeaf($leftIndexPage, $rightIndexPage, 11, 12, 3, $pageSize);

$tableHeader = SQLiteBTreePageHeader::parsePage($tablePlan->mergedPage, $pageSize);
$indexHeader = SQLiteBTreePageHeader::parsePage($indexPlan->mergedPage, $pageSize);

echo json_encode([
    'scenario' => 'application-btree-leaf-merge-plan',
    'applicationUse' => 'Preview a copied wp_options delete/rebalance path that merges underfilled table and autoload index leaf siblings, removes the parent divider, and marks the obsolete right sibling for freelist reuse without requiring ext/sqlite.',
    'tableMerge' => $tablePlan->toArray(),
    'tableRows' => array_map(
        static fn (SQLiteTableLeafCell $cell): array => [
            'rowid' => $cell->rowId,
            'option_name' => SQLiteRecord::parse($cell->payload)->values[1],
        ],
        SQLiteTableLeafCell::parsePageCells($tablePlan->mergedPage, $tableHeader),
    ),
    'indexMerge' => $indexPlan->toArray(),
    'indexRecords' => array_map(
        static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
        SQLiteIndexCell::parsePageCells($indexPlan->mergedPage, $indexHeader),
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
