<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteIndexPredicate.php';
require_once __DIR__ . '/../src/SQLiteIndexSkipScanPlan.php';
require_once __DIR__ . '/../src/SQLiteSkipScanStat4PartialOrderPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerExpressionSkipScanRangeCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePlannerSkipScanExpressionRangeCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLitePlannerSkipScanExpressionRangeCurrentSourceNextPlan;

$prepared = [
    'name' => 'prepared-main.app_settings',
    'schemaCookie' => 1490,
    'stat4Generation' => 71,
    'indexName' => 'idx_app_settings_load_policy_lower_key',
    'rootPage' => 14901,
    'skippedColumn' => 'load_policy',
    'rangeColumn' => 'key_name',
    'rangeExpression' => 'lower(key_name)',
    'rangeExpressionColumn' => '__expr_lower_key_name_range_recheck',
    'lowerInclusive' => 'module_',
    'upperBound' => 'module_t',
    'upperInclusive' => false,
    'collation' => 'BINARY',
    'coveringColumns' => ['load_policy', 'key_name', 'key_value', 'kind'],
    'rows' => [
        ['rowid' => 1, 'load_policy' => 'auto', 'key_name' => 'module_alpha', 'key_value' => 'a:1', 'kind' => 'module'],
        ['rowid' => 2, 'load_policy' => 'no', 'key_name' => 'module_mail', 'key_value' => 'a:2', 'kind' => 'module'],
    ],
    'stat4Samples' => [
        ['prefix' => 'auto', 'suffix' => 'module_alpha', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
        ['prefix' => 'no', 'suffix' => 'module_mail', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
    ],
];

$current = array_replace($prepared, [
    'name' => 'current-main.app_settings',
    'schemaCookie' => 1491,
    'stat4Generation' => 72,
    'rootPage' => 14909,
    'lowerInclusive' => 'module_d',
    'upperBound' => 'module_zeta',
    'upperInclusive' => true,
    'rows' => [
        ['rowid' => 1, 'load_policy' => 'auto', 'key_name' => 'module_delta', 'key_value' => 'a:3', 'kind' => 'module'],
        ['rowid' => 2, 'load_policy' => 'no', 'key_name' => 'module_zeta', 'key_value' => 'a:4', 'kind' => 'module'],
        ['rowid' => 3, 'load_policy' => 'yes', 'key_name' => 'module_delta', 'key_value' => 'stale', 'kind' => 'module', '__expr_lower_key_name_range_recheck' => 'module_archive_old_cache'],
    ],
    'stat4Samples' => [
        ['prefix' => 'auto', 'suffix' => 'module_delta', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
        ['prefix' => 'no', 'suffix' => 'module_zeta', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
        ['prefix' => 'yes', 'suffix' => 'module_delta', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
    ],
]);

$partial = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'module'),
    new SQLiteIndexPredicate('key_name', SQLiteIndexPredicate::IS_NOT_NULL),
]);

$plan = SQLitePlannerSkipScanExpressionRangeCurrentSourceNextPlan::materializeExpressionRangeRecheck(
    $prepared,
    $current,
    $partial,
    [
        ['operator' => '=', 'left' => ['column' => 'kind'], 'right' => 'module'],
        ['operator' => 'IS NOT NULL', 'left' => ['column' => 'key_name']],
        ['operator' => '>=', 'left' => ['expression' => 'lower(key_name)'], 'right' => 'module_'],
    ],
    [['expression' => 'load_policy'], ['expression' => 'lower(key_name)']],
    ['key_name', 'key_value', 'kind'],
);

echo json_encode([
    'status' => $plan['status'],
    'acceptedRowids' => $plan['expressionRangeAudit']['acceptedRowids'],
    'rejectedRowids' => $plan['expressionRangeAudit']['rejectedRowids'],
    'recheckOpcode' => $plan['selectedPlan']['expressionRangeRecheckOpcode'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
