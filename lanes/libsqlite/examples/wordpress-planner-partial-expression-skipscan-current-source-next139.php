<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLitePlannerPartialExpressionSkipScanCurrentSourceNextPlan;

$prepared = [
    'name' => 'prepared-main.wp_options@cookie1390',
    'schemaCookie' => 1390,
    'stat4Generation' => 41,
    'indexName' => 'idx_wp_options_autoload_lower_name_partial_next139',
    'rootPage' => 13901,
    'skippedColumn' => 'autoload',
    'rangeColumn' => 'option_name',
    'rangeExpression' => 'lower(option_name)',
    'rangeExpressionColumn' => '__expr_lower_option_name_next139',
    'lowerInclusive' => 'plugin_',
    'upperBound' => 'plugin_zzzz',
    'upperInclusive' => true,
    'collation' => 'BINARY',
    'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
    'rows' => [
        ['rowid' => 1, 'autoload' => 'auto', 'option_name' => 'plugin_alpha', 'option_value' => 'a:1', 'kind' => 'plugin'],
        ['rowid' => 2, 'autoload' => 'auto', 'option_name' => 'plugin_beta', 'option_value' => 'a:2', 'kind' => 'plugin'],
        ['rowid' => 3, 'autoload' => 'auto', 'option_name' => null, 'option_value' => 'null-name', 'kind' => 'plugin'],
        ['rowid' => 4, 'autoload' => 'lazy', 'option_name' => 'Plugin_Cache', 'option_value' => 'a:3', 'kind' => 'plugin'],
        ['rowid' => 6, 'autoload' => 'no', 'option_name' => 'plugin_forms', 'option_value' => 'a:4', 'kind' => 'plugin'],
        ['rowid' => 7, 'autoload' => 'no', 'option_name' => 'plugin_mail', 'option_value' => 'a:5', 'kind' => 'plugin'],
        ['rowid' => 8, 'autoload' => 'yes', 'option_name' => 'plugin_seo', 'option_value' => 'a:6', 'kind' => 'plugin'],
    ],
    'stat4Samples' => [
        ['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => 2, 'nLt' => 0, 'nDLt' => 0],
        ['prefix' => 'auto', 'suffix' => 'plugin_beta', 'nEq' => 2, 'nLt' => 1, 'nDLt' => 1],
        ['prefix' => 'lazy', 'suffix' => 'plugin_cache', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
        ['prefix' => 'no', 'suffix' => 'plugin_forms', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
        ['prefix' => 'no', 'suffix' => 'plugin_mail', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1],
        ['prefix' => 'yes', 'suffix' => 'plugin_seo', 'nEq' => 1, 'nLt' => 0, 'nDLt' => 0],
    ],
];

$current = $prepared;
$current['name'] = 'current-main.wp_options@cookie1391';
$current['schemaCookie'] = 1391;
$current['stat4Generation'] = 42;
$current['rootPage'] = 13909;
$current['rows'][] = ['rowid' => 9, 'autoload' => 'no', 'option_name' => 'plugin_security', 'option_value' => 'a:7', 'kind' => 'plugin'];
$current['rows'][] = ['rowid' => 10, 'autoload' => 'yes', 'option_name' => 'PLUGIN_ZETA', 'option_value' => 'a:8', 'kind' => 'plugin'];
$current['rows'][] = ['rowid' => 11, 'autoload' => 'yes', 'option_name' => null, 'option_value' => 'new-null', 'kind' => 'plugin'];
$current['stat4Samples'][] = ['prefix' => 'no', 'suffix' => 'plugin_security', 'nEq' => 1, 'nLt' => 2, 'nDLt' => 2];
$current['stat4Samples'][] = ['prefix' => 'yes', 'suffix' => 'plugin_zeta', 'nEq' => 1, 'nLt' => 1, 'nDLt' => 1];

$plan = SQLitePlannerPartialExpressionSkipScanCurrentSourceNextPlan::materializeNext139(
    $prepared,
    $current,
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin'),
    new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
        new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin'),
        new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::IS_NOT_NULL),
    ]),
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
    'partialPredicateChanged' => $plan['partialPredicateChanged'],
    'rowids' => $plan['currentSkipScanRowids'],
    'rejectedByPredicateChange' => $plan['currentRowsRejectedByPredicateChange'],
    'predicateOpcode' => $plan['predicateRecheckOpcode'],
];

assert($summary['status'] === 'partial-expression-skipscan-current-source-next139-ready');
assert($summary['selectedSource'] === 'current');
assert($summary['partialPredicateChanged'] === true);
assert($summary['rowids'] === [1, 2, 4, 6, 7, 9, 8, 10]);
assert($summary['rejectedByPredicateChange'] === [3, 11]);
assert($summary['predicateOpcode'] === 'IfNotPartialPredicate');

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
