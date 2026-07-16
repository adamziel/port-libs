<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeCellPayloadSplitPlan;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$tablePayload = SQLiteRecord::encode([
    null,
    '_transient_timeout_plugin_payload_split',
    str_repeat('plugin-cache-entry:', 95),
    'no',
]);
$tableLocal = SQLiteTableLeafCell::localPayloadLength(strlen($tablePayload), 512);
$tableOverflowPages = range(12, 12 + PortLibs\LibSqlite\SQLiteOverflowPage::requiredPageCount(strlen($tablePayload) - $tableLocal) - 1);
$tablePlan = SQLiteBTreeCellPayloadSplitPlan::tableLeaf(strlen($tablePayload), 512, $tableOverflowPages);

$indexPayload = SQLiteRecord::encode([
    str_repeat('_transient_timeout_plugin_payload_split_', 35),
    42,
]);
$indexLocal = SQLiteIndexCell::localPayloadLength(strlen($indexPayload), 512);
$indexOverflowPages = range(40, 40 + PortLibs\LibSqlite\SQLiteOverflowPage::requiredPageCount(strlen($indexPayload) - $indexLocal) - 1);
$indexPlan = SQLiteBTreeCellPayloadSplitPlan::index(strlen($indexPayload), 512, $indexOverflowPages);

echo json_encode([
    'applicationUse' => 'Plan SQLite b-tree local payload bytes and current/next overflow page links for copied wp_options table rows and option_name index entries before rebalance or delete materialization, without ext/sqlite.',
    'tableCell' => $tablePlan->toArray(),
    'indexCell' => $indexPlan->toArray(),
    'tableOverflowPayloadBytes' => array_sum(array_column($tablePlan->overflowLinks, 'payload_bytes')),
    'indexOverflowPayloadBytes' => array_sum(array_column($indexPlan->overflowLinks, 'payload_bytes')),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
