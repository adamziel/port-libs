<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLiteSkipScanStat4PartialOrderPlan;

$partial = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('kind', SQLiteIndexPredicate::EQUALS, 'plugin'),
    new SQLiteIndexPredicate('option_name', SQLiteIndexPredicate::GREATER_THAN_OR_EQUAL, 'plugin_'),
]);

$prepared = [
    'name' => 'prepared-main.wp_options@cookie1250',
    'schemaCookie' => 1250,
    'stat4Generation' => 12,
    'indexName' => 'idx_wp_options_autoload_plugin_covering',
    'rootPage' => 44,
    'skippedColumn' => 'autoload',
    'rangeColumn' => 'option_name',
    'lowerInclusive' => 'plugin_',
    'upperBound' => 'plugin_zzzz',
    'upperInclusive' => true,
    'collation' => 'NOCASE',
    'coveringColumns' => ['autoload', 'option_name', 'option_value', 'kind'],
    'rows' => [
        ['rowid' => 2, 'autoload' => 'auto', 'option_name' => 'plugin_alpha', 'option_value' => 'a:1', 'kind' => 'plugin'],
        ['rowid' => 3, 'autoload' => 'auto', 'option_name' => 'plugin_beta', 'option_value' => 'a:2', 'kind' => 'plugin'],
        ['rowid' => 7, 'autoload' => 'no', 'option_name' => 'plugin_gamma', 'option_value' => 'a:5', 'kind' => 'plugin'],
    ],
    'stat4Samples' => [
        ['prefix' => 'auto', 'suffix' => 'plugin_alpha', 'nEq' => 2, 'nLt' => 1, 'nDLt' => 1],
        ['prefix' => 'auto', 'suffix' => 'plugin_beta', 'nEq' => 2, 'nLt' => 3, 'nDLt' => 2],
        ['prefix' => 'no', 'suffix' => 'plugin_gamma', 'nEq' => 3, 'nLt' => 2, 'nDLt' => 1],
    ],
];

$current = $prepared;
$current['name'] = 'current-main.wp_options@cookie1251';
$current['schemaCookie'] = 1251;
$current['stat4Generation'] = 13;
$current['rootPage'] = 47;
$current['rows'][] = ['rowid' => 11, 'autoload' => 'no', 'option_name' => 'plugin_theta', 'option_value' => 'a:7', 'kind' => 'plugin'];
$current['stat4Samples'][] = ['prefix' => 'no', 'suffix' => 'plugin_theta', 'nEq' => 1, 'nLt' => 4, 'nDLt' => 2];

$plan = SQLiteSkipScanStat4PartialOrderPlan::coveringCurrentSource(
    $prepared,
    $current,
    $partial,
    [
        ['operator' => '=', 'left' => ['column' => 'kind'], 'right' => 'plugin'],
        ['operator' => '>=', 'left' => ['column' => 'option_name'], 'right' => 'plugin_'],
    ],
    [['column' => 'option_name']],
    ['option_name', 'option_value'],
);

$summary = [
    'selectedSource' => $plan['selectedSource'],
    'reprepareRequired' => $plan['reprepareRequired'],
    'rowids' => $plan['selectedPlan']['rowids'],
    'covering' => $plan['selectedPlan']['covering'],
    'tableSeekRequired' => $plan['selectedPlan']['tableSeekRequired'],
    'detail' => $plan['detail'],
];

assert($summary['selectedSource'] === 'current');
assert($summary['reprepareRequired'] === true);
assert($summary['rowids'] === [2, 3, 7, 11]);
assert($summary['covering'] === true);
assert($summary['tableSeekRequired'] === false);

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
