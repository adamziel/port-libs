<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLitePlannerExpressionSkipScanRangeCurrentSourceNextPlan;

$partial = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin'),
    new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::IS_NOT_NULL),
]);

$prepared = [
    'name' => 'prepared-main.wp_options@cookie1430',
    'schemaCookie' => 1430,
    'stat4Generation' => 61,
    'indexName' => 'idx_wp_options_autoload_lower_name_range_next143',
    'rootPage' => 14301,
    'skippedColumn' => 'autoload',
    'rangeColumn' => 'option_name',
    'rangeExpression' => 'lower(option_name)',
    'rangeExpressionColumn' => '__expr_lower_option_name_next143',
    'lowerInclusive' => 'plugin_',
    'upperBound' => 'plugin_t',
    'upperInclusive' => false,
    'collation' => 'BINARY',
    'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
    'rows' => [
        ['rowid' => 1, 'autoload' => 'auto', 'option_name' => 'plugin_alpha', 'option_value' => 'a:1', 'kind' => 'plugin'],
        ['rowid' => 2, 'autoload' => 'auto', 'option_name' => 'plugin_legacy', 'option_value' => 'old', 'kind' => 'plugin'],
        ['rowid' => 3, 'autoload' => 'no', 'option_name' => 'plugin_security', 'option_value' => 'a:6', 'kind' => 'plugin'],
    ],
    'stat4Samples' => [
        ['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => 2, 'nLt' => 0, 'nDLt' => 0],
        ['prefix' => 'auto', 'suffix' => 'plugin_legacy', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
        ['prefix' => 'no', 'suffix' => 'plugin_security', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
    ],
];

$current = $prepared;
$current['name'] = 'current-main.wp_options@cookie1431';
$current['schemaCookie'] = 1431;
$current['stat4Generation'] = 62;
$current['rootPage'] = 14309;
$current['lowerInclusive'] = 'plugin_d';
$current['upperBound'] = 'plugin_zeta';
$current['upperInclusive'] = true;
$current['rows'][] = ['rowid' => 4, 'autoload' => 'no', 'option_name' => 'plugin_zeta', 'option_value' => 'a:10', 'kind' => 'plugin'];
$current['stat4Samples'][] = ['prefix' => 'no', 'suffix' => 'plugin_zeta', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1];

$plan = SQLitePlannerExpressionSkipScanRangeCurrentSourceNextPlan::materializeNext143(
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
    'rangeFenceChanged' => $plan['rangeFenceChanged'],
    'rangeRejectedRowids' => $plan['rangeRejectedRowids'],
    'rangeAdmittedRowids' => $plan['rangeAdmittedRowids'],
    'currentRowids' => $plan['currentSkipScanRowids'],
    'upperOpcode' => $plan['rangeFence']['upperOpcode'],
];

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['status'] === 'expression-skipscan-range-current-source-next143-ready');
    assert($summary['selectedSource'] === 'current');
    assert($summary['rangeFenceChanged'] === true);
    assert($summary['rangeRejectedRowids'] === [1]);
    assert($summary['rangeAdmittedRowids'] === [4]);
    assert($summary['currentRowids'] === [2, 3, 4]);
    assert($summary['upperOpcode'] === 'IdxGT');
    echo "wordpress-planner-expression-skipscan-range-current-source-next143 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
