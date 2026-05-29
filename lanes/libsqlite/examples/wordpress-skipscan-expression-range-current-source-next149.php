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
    'name' => 'prepared-main.wp_options',
    'schemaCookie' => 1490,
    'stat4Generation' => 71,
    'indexName' => 'idx_wp_options_autoload_lower_name',
    'rootPage' => 14901,
    'skippedColumn' => 'autoload',
    'rangeColumn' => 'option_name',
    'rangeExpression' => 'lower(option_name)',
    'rangeExpressionColumn' => '__expr_lower_option_name_next149',
    'lowerInclusive' => 'plugin_',
    'upperBound' => 'plugin_t',
    'upperInclusive' => false,
    'collation' => 'BINARY',
    'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
    'rows' => [
        ['rowid' => 1, 'autoload' => 'auto', 'option_name' => 'plugin_alpha', 'option_value' => 'a:1', 'kind' => 'plugin'],
        ['rowid' => 2, 'autoload' => 'no', 'option_name' => 'plugin_mail', 'option_value' => 'a:2', 'kind' => 'plugin'],
    ],
    'stat4Samples' => [
        ['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
        ['prefix' => 'no', 'suffix' => 'plugin_mail', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
    ],
];

$current = array_replace($prepared, [
    'name' => 'current-main.wp_options',
    'schemaCookie' => 1491,
    'stat4Generation' => 72,
    'rootPage' => 14909,
    'lowerInclusive' => 'plugin_d',
    'upperBound' => 'plugin_zeta',
    'upperInclusive' => true,
    'rows' => [
        ['rowid' => 1, 'autoload' => 'auto', 'option_name' => 'plugin_delta', 'option_value' => 'a:3', 'kind' => 'plugin'],
        ['rowid' => 2, 'autoload' => 'no', 'option_name' => 'plugin_zeta', 'option_value' => 'a:4', 'kind' => 'plugin'],
        ['rowid' => 3, 'autoload' => 'yes', 'option_name' => 'plugin_delta', 'option_value' => 'stale', 'kind' => 'plugin', '__expr_lower_option_name_next149' => 'theme_mods_old_cache'],
    ],
    'stat4Samples' => [
        ['prefix' => 'auto', 'suffix' => 'plugin_delta', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
        ['prefix' => 'no', 'suffix' => 'plugin_zeta', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
        ['prefix' => 'yes', 'suffix' => 'plugin_delta', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
    ],
]);

$partial = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin'),
    new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::IS_NOT_NULL),
]);

$plan = SQLitePlannerSkipScanExpressionRangeCurrentSourceNextPlan::materializeNext149(
    $prepared,
    $current,
    $partial,
    [
        ['operator' => '=', 'left' => ['column' => 'kind'], 'right' => 'plugin'],
        ['operator' => 'IS NOT NULL', 'left' => ['column' => 'option_name']],
        ['operator' => '>=', 'left' => ['expression' => 'lower(option_name)'], 'right' => 'plugin_'],
    ],
    [['expression' => 'autoload'], ['expression' => 'lower(option_name)']],
    ['option_name', 'option_value', 'kind'],
);

echo json_encode([
    'status' => $plan['status'],
    'acceptedRowids' => $plan['expressionRangeAudit']['acceptedRowids'],
    'rejectedRowids' => $plan['expressionRangeAudit']['rejectedRowids'],
    'recheckOpcode' => $plan['selectedPlan']['expressionRangeRecheckOpcode'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
