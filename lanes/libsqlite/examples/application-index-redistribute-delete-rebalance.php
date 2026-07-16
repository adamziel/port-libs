<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeLeafRedistributionPlan;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteRecord;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$leftIndexPage = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['no', '_transient_cleanup', 40])),
], $pageSize);
$rightIndexPage = SQLiteIndexLeafPage::assemble([
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_a', 41])),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_b', 42])),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_c', 43])),
    SQLiteIndexCell::encode(SQLiteRecord::encode(['yes', 'autoload_d', 44])),
], $pageSize);

$plan = SQLiteBTreeLeafRedistributionPlan::indexLeaf($leftIndexPage, $rightIndexPage, 8, 9, 3, $pageSize);
$leftHeader = SQLiteBTreePageHeader::parsePage($plan->leftPage, $pageSize);
$rightHeader = SQLiteBTreePageHeader::parsePage($plan->rightPage, $pageSize);

echo json_encode([
    'scenario' => 'application-index-redistribute-delete-rebalance',
    'applicationUse' => 'Preview a copied wp_options autoload-index delete/rebalance path that redistributes cells from a fuller right sibling into an underfilled left sibling, updates the parent divider, and avoids placing either sibling on the freelist.',
    'redistribution' => $plan->toArray(),
    'leftIndexRecordsAfter' => array_map(
        static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
        SQLiteIndexCell::parsePageCells($plan->leftPage, $leftHeader),
    ),
    'rightIndexRecordsAfter' => array_map(
        static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
        SQLiteIndexCell::parsePageCells($plan->rightPage, $rightHeader),
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
