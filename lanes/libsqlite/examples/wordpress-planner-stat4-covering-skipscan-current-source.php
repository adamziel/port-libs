<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLitePlannerStat4CoveringSkipScanCurrentSourceNextPlan;

$partial = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin'),
    new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::IS_NOT_NULL),
]);

$prepared = [
    'name' => 'prepared-main.wp_options@cookie-prepared',
    'schemaCookie' => 170,
    'stat4Generation' => 77,
    'indexName' => 'idx_wp_options_autoload_lower_name_covering',
    'rootPage' => 701,
    'skippedColumn' => 'autoload',
    'rangeColumn' => 'option_name',
    'rangeExpression' => 'lower(option_name)',
    'rangeExpressionColumn' => '__expr_lower_option_name',
    'lowerInclusive' => 'plugin_',
    'upperBound' => 'plugin_zeta',
    'upperInclusive' => true,
    'collation' => 'BINARY',
    'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
    'rows' => [
        ['rowid' => 1, 'autoload' => 'auto', 'option_name' => 'plugin_alpha', 'option_value' => 'a:1', 'kind' => 'plugin'],
        ['rowid' => 2, 'autoload' => 'auto', 'option_name' => 'plugin_forms', 'option_value' => 'a:3', 'kind' => 'plugin'],
        ['rowid' => 3, 'autoload' => 'no', 'option_name' => 'plugin_seo', 'option_value' => 'a:6', 'kind' => 'plugin'],
    ],
    'stat4Samples' => [
        ['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => 2, 'nLt' => 0, 'nDLt' => 0],
        ['prefix' => 'auto', 'suffix' => 'plugin_forms', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
        ['prefix' => 'no', 'suffix' => 'plugin_seo', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
    ],
];
$current = $prepared;
$current['name'] = 'current-main.wp_options@cookie-current';
$current['schemaCookie'] = 1471;
$current['stat4Generation'] = 78;
$current['rootPage'] = 14709;
$current['lowerInclusive'] = 'plugin_d';
$current['upperBound'] = 'plugin_zip';
$current['collation'] = 'NOCASE';
$current['rows'][] = ['rowid' => 4, 'autoload' => 'auto', 'option_name' => 'plugin_delta', 'option_value' => 'a:9', 'kind' => 'plugin'];
$current['rows'][] = ['rowid' => 5, 'autoload' => 'no', 'option_name' => 'PLUGIN_ZIP', 'option_value' => 'a:11', 'kind' => 'plugin'];
$current['stat4Samples'][] = ['prefix' => 'auto', 'suffix' => 'plugin_delta', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1];
$current['stat4Samples'][] = ['prefix' => 'no', 'suffix' => 'plugin_zip', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1];

$plan = SQLitePlannerStat4CoveringSkipScanCurrentSourceNextPlan::materialize(
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
    'stat4SignatureChanged' => $plan['stat4SignatureChanged'],
    'addedStat4Samples' => array_column($plan['stat4SampleDelta']['added'], 'suffix'),
    'currentRowids' => $plan['currentSkipScanRowids'],
    'coveringColumns' => $plan['coveringCursorTape']['program'][3]['columns'],
];

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['status'] === 'stat4-covering-skipscan-current-source-ready');
    assert($summary['selectedSource'] === 'current');
    assert($summary['stat4SignatureChanged'] === true);
    assert($summary['addedStat4Samples'] === ['plugin_delta', 'plugin_zip']);
    assert($summary['currentRowids'] === [4, 2, 3, 5]);
    assert($summary['coveringColumns'] === ['option_name', 'option_value', 'kind', 'autoload', '__expr_lower_option_name']);
    echo "wordpress-planner-stat4-covering-skipscan-current-source self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
