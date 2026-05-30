<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLitePlannerStat4ExpressionSkipScanCurrentSourceNextPlan;

$partial = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin'),
    new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::IS_NOT_NULL),
]);

$prepared = [
    'name' => 'prepared-main.wp_options@cookie1370',
    'schemaCookie' => 1370,
    'stat4Generation' => 31,
    'indexName' => 'idx_wp_options_autoload_lower_name_next137',
    'rootPage' => 13701,
    'skippedColumn' => 'autoload',
    'rangeColumn' => 'option_name',
    'rangeExpression' => 'lower(option_name)',
    'rangeExpressionColumn' => '__expr_lower_option_name_next137',
    'lowerInclusive' => 'plugin_',
    'upperBound' => 'plugin_z',
    'upperInclusive' => false,
    'collation' => 'BINARY',
    'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
    'rows' => [
        ['rowid' => 1, 'autoload' => 'auto', 'option_name' => 'Plugin_Alpha', 'option_value' => 'a:1', 'kind' => 'plugin'],
        ['rowid' => 2, 'autoload' => 'auto', 'option_name' => 'plugin_beta', 'option_value' => 'a:2', 'kind' => 'plugin'],
        ['rowid' => 6, 'autoload' => 'no', 'option_name' => 'plugin_mail', 'option_value' => 'a:5', 'kind' => 'plugin'],
        ['rowid' => 8, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'a:6', 'kind' => 'plugin'],
    ],
    'stat4Samples' => [
        ['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => 2, 'nLt' => 0, 'nDLt' => 0],
        ['prefix' => 'auto', 'suffix' => 'plugin_beta', 'nEq' => 2, 'nLt' => 2, 'nDLt' => 1],
        ['prefix' => 'no', 'suffix' => 'plugin_mail', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
        ['prefix' => 'yes', 'suffix' => 'plugin_seo', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
    ],
];

$current = $prepared;
$current['name'] = 'current-main.wp_options@cookie1371';
$current['schemaCookie'] = 1371;
$current['stat4Generation'] = 32;
$current['rootPage'] = 13719;
$current['rows'][] = ['rowid' => 9, 'autoload' => 'no', 'option_name' => 'PLUGIN_SECURITY', 'option_value' => 'a:7', 'kind' => 'plugin'];
$current['stat4Samples'][] = ['prefix' => 'no', 'suffix' => 'plugin_security', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1];

$plan = SQLitePlannerStat4ExpressionSkipScanCurrentSourceNextPlan::materializeCurrentSource(
    $prepared,
    $current,
    $partial,
    [
        ['operator' => '=', 'left' => ['column' => 'kind'], 'right' => 'plugin'],
        ['operator' => 'IS NOT NULL', 'left' => ['column' => 'option_name']],
        ['operator' => '>=', 'left' => ['expression' => 'lower(option_name)'], 'right' => 'plugin_'],
    ],
    [
        ['expression' => 'autoload'],
        ['expression' => 'lower(option_name)'],
    ],
    ['option_name', 'option_value', 'kind'],
);

$summary = [
    'status' => $plan['status'],
    'selectedSource' => $plan['selectedSource'],
    'admittedRowids' => $plan['currentSkipScanAdmittedRowids'],
    'seekOpcode' => $plan['cursorTape']['program'][1]['opcode'],
    'rangeOpcode' => $plan['cursorTape']['program'][2]['opcode'],
    'detail' => $plan['detail'],
];

assert($summary['status'] === 'stat4-expression-skipscan-current-source-next137-ready');
assert($summary['selectedSource'] === 'current');
assert($summary['admittedRowids'] === [9]);
assert($summary['seekOpcode'] === 'SeekScan');
assert($summary['rangeOpcode'] === 'IdxGE');

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
