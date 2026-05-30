<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteIndexPredicate.php';
require_once __DIR__ . '/../src/SQLiteIndexSkipScanPlan.php';
require_once __DIR__ . '/../src/SQLiteSkipScanStat4PartialOrderPlan.php';

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLiteSkipScanStat4PartialOrderPlan;

$rows = [
    ['rowid' => 1, 'autoload' => 'auto', 'option_name' => 'plugin_alpha', 'kind' => 'plugin'],
    ['rowid' => 2, 'autoload' => 'auto', 'option_name' => 'plugin_beta', 'kind' => 'plugin'],
    ['rowid' => 3, 'autoload' => 'no', 'option_name' => 'plugin_alpha', 'kind' => 'plugin'],
    ['rowid' => 4, 'autoload' => 'no', 'option_name' => 'plugin_gamma', 'kind' => 'plugin'],
    ['rowid' => 5, 'autoload' => 'yes', 'option_name' => 'blogname', 'kind' => 'core'],
    ['rowid' => 6, 'autoload' => 'yes', 'option_name' => 'plugin_delta', 'kind' => 'plugin'],
];

$partial = new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::GREATER_THAN_OR_EQUAL, 'plugin_');
$terms = [
    ['operator' => '>=', 'left' => ['column' => 'option_name'], 'right' => 'plugin_alpha'],
];
$stat4 = [
    ['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => 2, 'nLt' => 0, 'nDLt' => 0],
    ['prefix' => 'no', 'suffix' => 'plugin_gamma', 'nEq' => 3, 'nLt' => 2, 'nDLt' => 1],
    ['prefix' => 'yes', 'suffix' => 'plugin_delta', 'nEq' => 4, 'nLt' => 3, 'nDLt' => 2],
];

$plan = SQLiteSkipScanStat4PartialOrderPlan::plan(
    $rows,
    'idx_wp_options_autoload_name_plugin',
    'autoload',
    'option_name',
    'plugin_',
    'plugin_zzzz',
    $partial,
    $terms,
    $stat4,
    [['column' => 'option_name']],
);

echo json_encode([
    'status' => $plan['status'],
    'rowids' => $plan['rowids'],
    'estimatedRows' => $plan['estimatedRows'],
    'orderByMode' => $plan['orderByMode'],
    'blockSortRequired' => $plan['blockSortRequired'],
    'detail' => $plan['detail'],
], JSON_PRETTY_PRINT) . PHP_EOL;
